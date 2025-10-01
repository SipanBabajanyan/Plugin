<?php
/**
 * Plugin Name: Simple Payment Links
 * Plugin URI: https://yourwebsite.com
 * Description: Простой плагин для создания ссылок оплаты без товаров
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: simple-payment-links
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Предотвращаем прямое обращение к файлу
if (!defined('ABSPATH')) {
    exit;
}

// Проверяем наличие WooCommerce
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Simple Payment Links</strong> требует установки и активации плагина WooCommerce.</p></div>';
    });
    return;
}

// Проверяем совместимость с HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Определяем константы плагина
define('SPL_VERSION', '1.0.0');
define('SPL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPL_PLUGIN_FILE', __FILE__);

/**
 * Основной класс плагина
 */
class Simple_Payment_Links {
    
    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Инициализация хуков WordPress
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Активация и деактивация плагина
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // AJAX обработчики
        add_action('wp_ajax_create_payment_link', array($this, 'ajax_create_payment_link'));
        add_action('wp_ajax_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_nopriv_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_delete_payment_link', array($this, 'ajax_delete_payment_link'));
        
        // Шорткод для страницы оплаты
        add_shortcode('simple_payment', array($this, 'render_payment_page'));
    }
    
    /**
     * Инициализация плагина
     */
    public function init() {
        // Загружаем переводы
        load_plugin_textdomain('simple-payment-links', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Подключение скриптов для фронтенда
     */
    public function enqueue_scripts() {
        if (is_page('payment')) {
            wp_enqueue_style('spl-style', SPL_PLUGIN_URL . 'assets/style.css', array(), SPL_VERSION);
            wp_enqueue_script('spl-script', SPL_PLUGIN_URL . 'assets/script.js', array('jquery'), SPL_VERSION, true);
            
            // Передаем AJAX данные
            wp_localize_script('spl-script', 'splAjax', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('spl_nonce')
            ));
        }
    }
    
    /**
     * Подключение скриптов для админки
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'simple-payment-links') !== false) {
            wp_enqueue_style('spl-admin-style', SPL_PLUGIN_URL . 'assets/admin.css', array(), SPL_VERSION);
            wp_enqueue_script('spl-admin-script', SPL_PLUGIN_URL . 'assets/admin.js', array('jquery'), SPL_VERSION, true);
        }
    }
    
    /**
     * Добавить меню в админку
     */
    public function add_admin_menu() {
        add_menu_page(
            'Payment Links',
            'Payment Links',
            'manage_options',
            'simple-payment-links',
            array($this, 'admin_page'),
            'dashicons-admin-links',
            56
        );
    }
    
    /**
     * Страница админки
     */
    public function admin_page() {
        if (isset($_POST['create_link'])) {
            $this->create_payment_link();
        }
        
        // Показываем уведомление о создании ссылки
        if (isset($_GET['created']) && $_GET['created'] == '1') {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Ссылка успешно создана!</p></div>';
            });
        }
        
        $links = $this->get_all_links();
        include SPL_PLUGIN_DIR . 'templates/admin.php';
    }
    
    /**
     * Создать ссылку оплаты
     */
    public function create_payment_link() {
        $amount = floatval($_POST['amount']);
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_text_field($_POST['description']);
        
        if ($amount <= 0) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Сумма должна быть больше нуля!</p></div>';
            });
            return;
        }
        
        if (empty($title)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Название обязательно!</p></div>';
            });
            return;
        }
        
        // Создаем только ссылку (не заказ)
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_payment_links';
        
        $token = $this->generate_token();
        $payment_url = home_url('/payment/?token=' . $token);
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'token' => $token,
                'amount' => $amount,
                'description' => $title,
                'customer_name' => '',
                'customer_email' => '',
                'status' => 'active',
                'order_id' => NULL,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result) {
            add_action('admin_notices', function() use ($payment_url) {
                echo '<div class="notice notice-success"><p>Ссылка создана: <a href="' . esc_url($payment_url) . '" target="_blank">' . esc_url($payment_url) . '</a></p></div>';
            });
            
            // Перенаправляем на ту же страницу, чтобы обновить список
            wp_redirect(add_query_arg(array('created' => '1'), admin_url('admin.php?page=simple-payment-links')));
            exit;
        } else {
            global $wpdb;
            add_action('admin_notices', function() use ($wpdb) {
                echo '<div class="notice notice-error"><p>Ошибка создания ссылки: ' . esc_html($wpdb->last_error) . '</p></div>';
            });
        }
    }
    
    /**
     * AJAX создание ссылки
     */
    public function ajax_create_payment_link() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $amount = floatval($_POST['amount']);
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_text_field($_POST['description']);
        
        if ($amount <= 0) {
            wp_send_json_error('Сумма должна быть больше нуля');
        }
        
        if (empty($title)) {
            wp_send_json_error('Название обязательно');
        }
        
        // Создаем только ссылку (не заказ)
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_payment_links';
        
        $token = $this->generate_token();
        $payment_url = home_url('/payment/?token=' . $token);
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'token' => $token,
                'amount' => $amount,
                'description' => $title,
                'customer_name' => '',
                'customer_email' => '',
                'status' => 'active',
                'order_id' => NULL,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'link' => $payment_url,
                'message' => 'Ссылка создана успешно'
            ));
        } else {
            wp_send_json_error('Ошибка создания ссылки');
        }
    }
    
    /**
     * AJAX обработка платежа
     */
    public function ajax_process_payment() {
        $token = sanitize_text_field($_POST['token']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        
        $link_data = $this->get_link_by_token($token);
        
        if (!$link_data) {
            wp_send_json_error('Ссылка не найдена');
        }
        
        // Создаем заказ только при оплате
        $order_id = $this->create_order_from_link($link_data, $payment_method);
        
        if ($order_id) {
            $order = wc_get_order($order_id);
            $payment_url = $order->get_checkout_payment_url();
            
            // Обновляем ссылку с ID заказа
            global $wpdb;
            $table_name = $wpdb->prefix . 'simple_payment_links';
            $wpdb->update(
                $table_name,
                array('order_id' => $order_id),
                array('token' => $token),
                array('%d'),
                array('%s')
            );
            
            wp_send_json_success(array(
                'order_id' => $order_id,
                'payment_url' => $payment_url
            ));
        } else {
            wp_send_json_error('Ошибка создания заказа');
        }
    }
    
    /**
     * AJAX удаление ссылки
     */
    public function ajax_delete_payment_link() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $link_id = intval($_POST['link_id']);
        
        if (!$link_id) {
            wp_send_json_error('Invalid link ID');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_payment_links';
        
        // Проверяем, существует ли ссылка
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE id = %d",
            $link_id
        ));
        
        if (!$exists) {
            wp_send_json_error('Ссылка не найдена');
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $link_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Ссылка удалена');
        } else {
            wp_send_json_error('Ошибка удаления ссылки: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Рендер страницы оплаты
     */
    public function render_payment_page($atts) {
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        if (!$token) {
            return '<div class="spl-error">Неверная ссылка для оплаты.</div>';
        }
        
        $link_data = $this->get_link_by_token($token);
        
        if (!$link_data || $link_data->status !== 'active') {
            return '<div class="spl-error">Ссылка для оплаты не найдена или неактивна.</div>';
        }
        
        ob_start();
        include SPL_PLUGIN_DIR . 'templates/payment.php';
        return ob_get_clean();
    }
    
    /**
     * Получить ссылку по токену
     */
    private function get_link_by_token($token) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_payment_links';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s",
            $token
        ));
    }
    
    /**
     * Создание заказа из ссылки
     */
    private function create_order_from_link($link_data, $payment_method) {
        try {
            $order = wc_create_order();
            
            if (is_wp_error($order)) {
                return false;
            }
            
            // Добавляем позицию как fee
            $item = new WC_Order_Item_Fee();
            $item->set_name($link_data->description ?: 'Платеж по ссылке');
            $item->set_amount($link_data->amount);
            $item->set_total($link_data->amount);
            $order->add_item($item);
            
            // Устанавливаем метод оплаты
            $order->set_payment_method($payment_method);
            $order->set_status('pending');
            
            // Добавляем мета-данные
            $order->update_meta_data('_simple_payment_token', $link_data->token);
            $order->update_meta_data('_simple_payment', 'yes');
            
            // Рассчитываем и сохраняем
            $order->calculate_totals();
            $order->save();
            
            return $order->get_id();
            
        } catch (Exception $e) {
            error_log('Simple Payment Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Прямое создание заказа (без промежуточной страницы)
     */
    private function create_order_direct($amount, $title, $description = '') {
        try {
            $order = wc_create_order();
            
            if (is_wp_error($order)) {
                return false;
            }
            
            // Добавляем позицию как fee
            $item = new WC_Order_Item_Fee();
            $item->set_name($title);
            $item->set_amount($amount);
            $item->set_total($amount);
            $order->add_item($item);
            
            // Устанавливаем статус
            $order->set_status('pending');
            
            // Добавляем мета-данные
            $token = $this->generate_token();
            $order->update_meta_data('_simple_payment_token', $token);
            $order->update_meta_data('_simple_payment', 'yes');
            $order->update_meta_data('_simple_payment_title', $title);
            if ($description) {
                $order->update_meta_data('_simple_payment_description', $description);
            }
            
            // Рассчитываем и сохраняем
            $order->calculate_totals();
            $order->save();
            
            return $order->get_id();
            
        } catch (Exception $e) {
            error_log('Simple Payment Error: ' . $e->getMessage());
            return false;
        }
    }
    
    
    /**
     * Получить все ссылки
     */
    private function get_all_links() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'simple_payment_links';
        
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        
        // Если таблица не существует, создаем ее
        if ($wpdb->last_error) {
            $this->create_tables();
            $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        }
        
        return $results ? $results : array();
    }
    
    /**
     * Генерация токена
     */
    private function generate_token() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Активация плагина
     */
    public function activate() {
        // Принудительно создаем таблицы
        $this->create_tables();
        $this->create_payment_page();
        
        // Очищаем кеш
        wp_cache_flush();
    }
    
    /**
     * Создание таблиц
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'simple_payment_links';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            token varchar(32) NOT NULL,
            amount decimal(10,2) NOT NULL,
            description text,
            customer_name varchar(255),
            customer_email varchar(255),
            status varchar(20) DEFAULT 'active',
            order_id int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY order_id (order_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Создание страницы оплаты
     */
    private function create_payment_page() {
        $existing_page = get_page_by_path('payment');
        
        if (!$existing_page) {
            $page_data = array(
                'post_title' => 'Payment',
                'post_name' => 'payment',
                'post_content' => '[simple_payment]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1
            );
            
            wp_insert_post($page_data);
        }
    }
    
}

// Инициализируем плагин
Simple_Payment_Links::get_instance();

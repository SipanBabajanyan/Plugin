<?php
/**
 * Plugin Name: Simple Payment Links
 * Plugin URI: https://yourwebsite.com
 * Description: Создает простые ссылки для оплаты произвольных сумм без товаров и форм checkout
 * Version: 2.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: subscription-link
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
        echo '<div class="notice notice-error"><p><strong>Subscription Link Payment</strong> требует установки и активации плагина WooCommerce.</p></div>';
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
define('SUBSCRIPTION_LINK_VERSION', '2.0.0');
define('SUBSCRIPTION_LINK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SUBSCRIPTION_LINK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SUBSCRIPTION_LINK_PLUGIN_FILE', __FILE__);

/**
 * Основной класс плагина
 */
class Subscription_Link_Payment {
    
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
        $this->load_dependencies();
    }
    
    /**
     * Инициализация хуков WordPress
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Активация и деактивация плагина
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Загрузка зависимостей
     */
    private function load_dependencies() {
        require_once SUBSCRIPTION_LINK_PLUGIN_DIR . 'includes/class-simple-payment.php';
        require_once SUBSCRIPTION_LINK_PLUGIN_DIR . 'includes/class-admin-interface.php';
    }
    
    /**
     * Инициализация плагина
     */
    public function init() {
        // Загружаем переводы
        load_plugin_textdomain('subscription-link', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Инициализируем компоненты (только простые платежи)
        Subscription_Link_Simple_Payment::get_instance();
        Subscription_Link_Admin_Interface::get_instance();
    }
    
    /**
     * Подключение скриптов для фронтенда
     */
    public function enqueue_scripts() {
        if (is_page('subscription-payment')) {
            wp_enqueue_style(
                'subscription-link-style',
                SUBSCRIPTION_LINK_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                SUBSCRIPTION_LINK_VERSION
            );
            
            wp_enqueue_script(
                'subscription-link-script',
                SUBSCRIPTION_LINK_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                SUBSCRIPTION_LINK_VERSION,
                true
            );
            
            // Передаем AJAX данные
            wp_localize_script('subscription-link-script', 'subscriptionLinkAjax', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('subscription_link_nonce'),
                'messages' => array(
                    'processing' => __('Обработка платежа...', 'subscription-link'),
                    'error' => __('Произошла ошибка. Попробуйте еще раз.', 'subscription-link'),
                    'success' => __('Платеж успешно обработан!', 'subscription-link')
                )
            ));
        }
    }
    
    /**
     * Подключение скриптов для админки
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'subscription-link') !== false) {
            wp_enqueue_style(
                'subscription-link-admin-style',
                SUBSCRIPTION_LINK_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                SUBSCRIPTION_LINK_VERSION
            );
            
            wp_enqueue_script(
                'subscription-link-admin-script',
                SUBSCRIPTION_LINK_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                SUBSCRIPTION_LINK_VERSION,
                true
            );
        }
    }
    
    /**
     * Активация плагина
     */
    public function activate() {
        // Создаем необходимые таблицы
        $this->create_tables();
        
        // Создаем страницу быстрой оплаты
        $this->create_payment_page();
        
        // Устанавливаем версию плагина
        update_option('subscription_link_version', SUBSCRIPTION_LINK_VERSION);
        
        // Создаем правила перезаписи URL
        flush_rewrite_rules();
    }
    
    /**
     * Деактивация плагина
     */
    public function deactivate() {
        // Очищаем правила перезаписи URL
        flush_rewrite_rules();
    }
    
    /**
     * Создание необходимых таблиц
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            token varchar(255) NOT NULL,
            product_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            customer_email varchar(255),
            customer_name varchar(255),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY product_id (product_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Создание страниц оплаты
     */
    private function create_payment_page() {
        // Создаем страницу для подписок
        $this->create_page('Subscription Payment', 'subscription-payment', '[subscription_payment_form]');
        
        // Создаем страницу для простых платежей
        $this->create_page('Simple Payment', 'simple-payment', '[simple_payment_link]');
    }
    
    /**
     * Создание страницы
     */
    private function create_page($title, $slug, $content) {
        $existing_page = get_page_by_path($slug);
        
        if (!$existing_page) {
            $page_data = array(
                'post_title' => $title,
                'post_name' => $slug,
                'post_content' => $content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1
            );
            
            wp_insert_post($page_data);
        }
    }
}

// Инициализируем плагин
Subscription_Link_Payment::get_instance();

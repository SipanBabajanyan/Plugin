<?php
/**
 * Класс админ-интерфейса
 */

if (!defined('ABSPATH')) {
    exit;
}

class Subscription_Link_Admin_Interface {
    
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
     * Инициализация хуков
     */
    private function init_hooks() {
        // Добавляем меню в админку
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Добавляем страницу настроек
        add_action('admin_init', array($this, 'register_settings'));
        
        // Добавляем мета-боксы
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Обработка AJAX запросов
        add_action('wp_ajax_subscription_link_bulk_action', array($this, 'handle_bulk_action'));
    }
    
    /**
     * Добавить меню в админку
     */
    public function add_admin_menu() {
        add_menu_page(
            'Subscription Links',
            'Subscription Links',
            'manage_woocommerce',
            'subscription-links',
            array($this, 'admin_page'),
            'dashicons-admin-links',
            56
        );
        
        add_submenu_page(
            'subscription-links',
            'All Subscriptions',
            'All Subscriptions',
            'manage_woocommerce',
            'subscription-links',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'subscription-links',
            'Create New',
            'Create New',
            'manage_woocommerce',
            'subscription-links-create',
            array($this, 'create_page')
        );
        
        add_submenu_page(
            'subscription-links',
            'Simple Payments',
            'Simple Payments',
            'manage_woocommerce',
            'subscription-links-simple',
            array($this, 'simple_payments_page')
        );
        
        add_submenu_page(
            'subscription-links',
            'Settings',
            'Settings',
            'manage_woocommerce',
            'subscription-links-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Регистрация настроек
     */
    public function register_settings() {
        register_setting('subscription_link_settings', 'subscription_link_options');
        
        add_settings_section(
            'subscription_link_general',
            'General Settings',
            array($this, 'settings_section_callback'),
            'subscription-links-settings'
        );
        
        add_settings_field(
            'default_currency',
            'Default Currency',
            array($this, 'currency_field_callback'),
            'subscription-links-settings',
            'subscription_link_general'
        );
        
        add_settings_field(
            'email_notifications',
            'Email Notifications',
            array($this, 'email_notifications_callback'),
            'subscription-links-settings',
            'subscription_link_general'
        );
    }
    
    /**
     * Основная страница админки
     */
    public function admin_page() {
        $subscription_manager = Subscription_Link_Manager::get_instance();
        
        // Обработка действий
        if (isset($_POST['action']) && $_POST['action'] === 'bulk_action') {
            $this->handle_bulk_action();
        }
        
        // Получаем подписки
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $subscriptions = $subscription_manager->get_active_subscriptions($per_page, $offset);
        $total_subscriptions = $this->get_total_subscriptions();
        
        include SUBSCRIPTION_LINK_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    /**
     * Страница создания новой подписки
     */
    public function create_page() {
        // Получаем товары для подписки
        $products = $this->get_subscription_products();
        
        include SUBSCRIPTION_LINK_PLUGIN_DIR . 'templates/admin-create.php';
    }
    
    /**
     * Страница простых платежей
     */
    public function simple_payments_page() {
        include SUBSCRIPTION_LINK_PLUGIN_DIR . 'templates/admin-simple-payments.php';
    }
    
    /**
     * Страница настроек
     */
    public function settings_page() {
        include SUBSCRIPTION_LINK_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Добавить мета-боксы
     */
    public function add_meta_boxes() {
        add_meta_box(
            'subscription_link_meta',
            'Subscription Link',
            array($this, 'subscription_meta_box'),
            'product',
            'side',
            'high'
        );
    }
    
    /**
     * Мета-бокс подписки
     */
    public function subscription_meta_box($post) {
        $is_subscription = get_post_meta($post->ID, '_is_subscription_product', true);
        
        if ($is_subscription === 'yes') {
            $subscription_manager = Subscription_Link_Manager::get_instance();
            $link = $subscription_manager->get_or_create_subscription_link($post->ID);
            
            if ($link) {
                echo '<p><strong>Subscription Link:</strong></p>';
                echo '<input type="text" value="' . esc_url($link) . '" readonly style="width: 100%;" onclick="this.select();">';
                echo '<p><a href="' . esc_url($link) . '" target="_blank" class="button">Test Link</a></p>';
            }
        } else {
            echo '<p>This product is not marked as a subscription product.</p>';
            echo '<p>Enable "Товар для подписки" in the product data to create a subscription link.</p>';
        }
    }
    
    /**
     * Обработка массовых действий
     */
    public function handle_bulk_action() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'subscription_link_bulk_action')) {
            wp_die('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $tokens = array_map('sanitize_text_field', $_POST['subscription_tokens']);
        
        $subscription_manager = Subscription_Link_Manager::get_instance();
        $processed = 0;
        
        foreach ($tokens as $token) {
            switch ($action) {
                case 'deactivate':
                    if ($subscription_manager->deactivate_subscription_link($token)) {
                        $processed++;
                    }
                    break;
                case 'delete':
                    if ($subscription_manager->delete_subscription($token)) {
                        $processed++;
                    }
                    break;
            }
        }
        
        $message = sprintf('Processed %d subscriptions', $processed);
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        });
    }
    
    /**
     * Получить товары для подписки
     */
    private function get_subscription_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_is_subscription_product',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        );
        
        $products = get_posts($args);
        $result = array();
        
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            $result[] = array(
                'id' => $product->ID,
                'title' => $product->post_title,
                'price' => $wc_product->get_price(),
                'currency' => get_woocommerce_currency()
            );
        }
        
        return $result;
    }
    
    /**
     * Получить общее количество подписок
     */
    private function get_total_subscriptions() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 1");
    }
    
    /**
     * Callback для секции настроек
     */
    public function settings_section_callback() {
        echo '<p>Configure general settings for subscription links.</p>';
    }
    
    /**
     * Callback для поля валюты
     */
    public function currency_field_callback() {
        $options = get_option('subscription_link_options');
        $currency = isset($options['default_currency']) ? $options['default_currency'] : get_woocommerce_currency();
        
        $currencies = array(
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'RUB' => 'Russian Ruble',
            'UAH' => 'Ukrainian Hryvnia'
        );
        
        echo '<select name="subscription_link_options[default_currency]">';
        foreach ($currencies as $code => $name) {
            $selected = selected($currency, $code, false);
            echo '<option value="' . esc_attr($code) . '"' . $selected . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }
    
    /**
     * Callback для уведомлений по email
     */
    public function email_notifications_callback() {
        $options = get_option('subscription_link_options');
        $enabled = isset($options['email_notifications']) ? $options['email_notifications'] : 1;
        
        echo '<input type="checkbox" name="subscription_link_options[email_notifications]" value="1" ' . checked($enabled, 1, false) . '>';
        echo '<label>Enable email notifications for payments</label>';
    }
    
    /**
     * Получить статистику
     */
    public function get_stats() {
        $subscription_manager = Subscription_Link_Manager::get_instance();
        return $subscription_manager->get_overall_stats();
    }
}

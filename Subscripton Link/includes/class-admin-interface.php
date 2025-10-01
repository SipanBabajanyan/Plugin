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
            'Payment Links',
            'Payment Links',
            'manage_woocommerce',
            'payment-links',
            array($this, 'simple_payments_page'),
            'dashicons-admin-links',
            56
        );
        
        add_submenu_page(
            'payment-links',
            'All Payments',
            'All Payments',
            'manage_woocommerce',
            'payment-links',
            array($this, 'simple_payments_page')
        );
        
        add_submenu_page(
            'payment-links',
            'Settings',
            'Settings',
            'manage_woocommerce',
            'payment-links-settings',
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
     * Основная страница админки (теперь простые платежи)
     */
    public function admin_page() {
        $this->simple_payments_page();
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
    
    // Убраны мета-боксы для товаров - больше не нужны
    
    // Упрощенный класс - убраны сложные методы с товарами
    
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
     * Получить статистику (упрощенная версия)
     */
    public function get_stats() {
        return array(
            'total_payments' => 0,
            'active_payments' => 0,
            'total_revenue' => 0
        );
    }
}

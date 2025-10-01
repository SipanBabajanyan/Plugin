<?php
/**
 * Класс управления подписками и токенами
 */

if (!defined('ABSPATH')) {
    exit;
}

class Subscription_Link_Manager {
    
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
        // AJAX обработчики для админки
        add_action('wp_ajax_create_subscription_link', array($this, 'ajax_create_subscription_link'));
        add_action('wp_ajax_deactivate_subscription_link', array($this, 'ajax_deactivate_subscription_link'));
        add_action('wp_ajax_get_subscription_stats', array($this, 'ajax_get_subscription_stats'));
    }
    
    /**
     * Создать или получить ссылку подписки для товара
     */
    public function get_or_create_subscription_link($product_id, $customer_email = '', $customer_name = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        
        // Проверяем, есть ли уже активная ссылка для этого товара
        $existing_link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d AND is_active = 1",
            $product_id
        ));
        
        if ($existing_link) {
            return $this->get_subscription_url($existing_link->token);
        }
        
        // Создаем новую ссылку
        $token = $this->generate_secure_token();
        
        // Получаем данные товара
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Получаем сумму подписки
        $subscription_amount = get_post_meta($product_id, '_subscription_amount', true);
        if (empty($subscription_amount)) {
            $subscription_amount = $product->get_price();
        }
        
        // Вставляем новую запись
        $result = $wpdb->insert(
            $table_name,
            array(
                'token' => $token,
                'product_id' => $product_id,
                'amount' => $subscription_amount,
                'currency' => get_woocommerce_currency(),
                'customer_email' => $customer_email,
                'customer_name' => $customer_name,
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%f', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $this->get_subscription_url($token);
    }
    
    /**
     * Создать ссылку подписки через AJAX
     */
    public function ajax_create_subscription_link() {
        // Проверяем права доступа
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        
        // Проверяем nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subscription_link_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $product_id = intval($_POST['product_id']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $customer_name = sanitize_text_field($_POST['customer_name']);
        
        $link = $this->get_or_create_subscription_link($product_id, $customer_email, $customer_name);
        
        if ($link) {
            wp_send_json_success(array(
                'link' => $link,
                'message' => 'Ссылка подписки создана успешно'
            ));
        } else {
            wp_send_json_error('Ошибка создания ссылки подписки');
        }
    }
    
    /**
     * Деактивировать ссылку подписки
     */
    public function ajax_deactivate_subscription_link() {
        // Проверяем права доступа
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        
        // Проверяем nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subscription_link_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $token = sanitize_text_field($_POST['token']);
        
        $result = $this->deactivate_subscription_link($token);
        
        if ($result) {
            wp_send_json_success('Ссылка подписки деактивирована');
        } else {
            wp_send_json_error('Ошибка деактивации ссылки');
        }
    }
    
    /**
     * Получить статистику подписки
     */
    public function ajax_get_subscription_stats() {
        // Проверяем права доступа
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        
        // Проверяем nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subscription_link_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $token = sanitize_text_field($_POST['token']);
        $stats = $this->get_subscription_stats($token);
        
        wp_send_json_success($stats);
    }
    
    /**
     * Деактивировать ссылку подписки
     */
    public function deactivate_subscription_link($token) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        
        $result = $wpdb->update(
            $table_name,
            array('is_active' => 0),
            array('token' => $token),
            array('%d'),
            array('%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Получить статистику подписки
     */
    public function get_subscription_stats($token) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        $payments_table = $wpdb->prefix . 'subscription_payments_log';
        
        // Получаем данные подписки
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s",
            $token
        ));
        
        if (!$subscription) {
            return false;
        }
        
        // Получаем статистику платежей
        $payments_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_payments,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
                MAX(created_at) as last_payment_date
             FROM $payments_table 
             WHERE subscription_token = %s",
            $token
        ));
        
        return array(
            'subscription' => $subscription,
            'payments' => $payments_stats
        );
    }
    
    /**
     * Получить URL подписки
     */
    public function get_subscription_url($token) {
        $payment_page_url = home_url('/subscription-payment/');
        return add_query_arg('token', $token, $payment_page_url);
    }
    
    /**
     * Генерация безопасного токена
     */
    private function generate_secure_token($length = 32) {
        // Используем криптографически стойкий генератор
        $bytes = random_bytes($length);
        return bin2hex($bytes);
    }
    
    /**
     * Валидация токена
     */
    public function validate_token($token) {
        if (empty($token) || strlen($token) !== 64) {
            return false;
        }
        
        // Проверяем формат (hex)
        if (!ctype_xdigit($token)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Получить все активные подписки
     */
    public function get_active_subscriptions($limit = 20, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT sl.*, p.post_title as product_name 
             FROM $table_name sl
             LEFT JOIN {$wpdb->posts} p ON sl.product_id = p.ID
             WHERE sl.is_active = 1
             ORDER BY sl.created_at DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
        
        return $subscriptions;
    }
    
    /**
     * Получить подписку по токену
     */
    public function get_subscription_by_token($token) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT sl.*, p.post_title as product_name 
             FROM $table_name sl
             LEFT JOIN {$wpdb->posts} p ON sl.product_id = p.ID
             WHERE sl.token = %s",
            $token
        ));
    }
    
    /**
     * Обновить данные подписки
     */
    public function update_subscription($token, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        
        $allowed_fields = array('amount', 'currency', 'customer_email', 'customer_name', 'is_active');
        $update_data = array();
        $update_format = array();
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $update_data[$field] = $value;
                $update_format[] = $field === 'amount' ? '%f' : '%s';
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('token' => $token),
            $update_format,
            array('%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Удалить подписку (мягкое удаление)
     */
    public function delete_subscription($token) {
        return $this->deactivate_subscription_link($token);
    }
    
    /**
     * Получить статистику всех подписок
     */
    public function get_overall_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        $payments_table = $wpdb->prefix . 'subscription_payments_log';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_subscriptions,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_subscriptions,
                SUM(amount) as total_revenue
             FROM $table_name"
        );
        
        $payments_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_payments
             FROM $payments_table"
        );
        
        return array_merge((array)$stats, (array)$payments_stats);
    }
}

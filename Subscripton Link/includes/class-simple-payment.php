<?php
/**
 * Класс для простых платежей без товаров
 */

if (!defined('ABSPATH')) {
    exit;
}

class Subscription_Link_Simple_Payment {
    
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
        // Шорткод для простой оплаты
        add_shortcode('simple_payment_link', array($this, 'render_simple_payment'));
        
        // AJAX обработчики
        add_action('wp_ajax_create_simple_payment', array($this, 'ajax_create_simple_payment'));
        add_action('wp_ajax_nopriv_create_simple_payment', array($this, 'ajax_create_simple_payment'));
        
        add_action('wp_ajax_process_simple_payment', array($this, 'ajax_process_simple_payment'));
        add_action('wp_ajax_nopriv_process_simple_payment', array($this, 'ajax_process_simple_payment'));
    }
    
    /**
     * Создать простую ссылку на оплату
     */
    public function create_simple_payment_link($amount, $description = '', $customer_email = '', $customer_name = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'simple_payment_links';
        
        // Создаем таблицу если не существует
        $this->create_simple_payments_table();
        
        // Генерируем токен
        $token = $this->generate_secure_token();
        
        // Вставляем запись
        $result = $wpdb->insert(
            $table_name,
            array(
                'token' => $token,
                'amount' => $amount,
                'currency' => get_woocommerce_currency(),
                'description' => $description,
                'customer_email' => $customer_email,
                'customer_name' => $customer_name,
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $this->get_simple_payment_url($token);
    }
    
    /**
     * Получить URL простой оплаты
     */
    public function get_simple_payment_url($token) {
        $payment_page_url = home_url('/simple-payment/');
        return add_query_arg('token', $token, $payment_page_url);
    }
    
    /**
     * Рендер формы простой оплаты
     */
    public function render_simple_payment($atts) {
        $atts = shortcode_atts(array(
            'token' => '',
            'amount' => 0,
            'description' => ''
        ), $atts);
        
        // Если токен не передан, получаем из URL
        if (empty($atts['token'])) {
            $atts['token'] = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        }
        
        if (empty($atts['token'])) {
            return '<div class="simple-payment-error">Неверная ссылка для оплаты.</div>';
        }
        
        // Получаем данные платежа
        $payment_data = $this->get_payment_by_token($atts['token']);
        
        if (!$payment_data) {
            return '<div class="simple-payment-error">Ссылка для оплаты не найдена или неактивна.</div>';
        }
        
        // Рендерим форму
        ob_start();
        include SUBSCRIPTION_LINK_PLUGIN_DIR . 'templates/simple-payment.php';
        return ob_get_clean();
    }
    
    /**
     * Получить данные платежа по токену
     */
    public function get_payment_by_token($token) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'simple_payment_links';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND is_active = 1",
            $token
        ));
    }
    
    /**
     * AJAX создание простой ссылки
     */
    public function ajax_create_simple_payment() {
        // Проверяем права доступа
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        
        // Проверяем nonce
        if (!wp_verify_nonce($_POST['nonce'], 'simple_payment_nonce')) {
            wp_die('Security check failed');
        }
        
        $amount = floatval($_POST['amount']);
        $description = sanitize_text_field($_POST['description']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $customer_name = sanitize_text_field($_POST['customer_name']);
        
        if ($amount <= 0) {
            wp_send_json_error('Сумма должна быть больше нуля');
        }
        
        $link = $this->create_simple_payment_link($amount, $description, $customer_email, $customer_name);
        
        if ($link) {
            wp_send_json_success(array(
                'link' => $link,
                'message' => 'Ссылка для оплаты создана успешно'
            ));
        } else {
            wp_send_json_error('Ошибка создания ссылки');
        }
    }
    
    /**
     * AJAX обработка простой оплаты
     */
    public function ajax_process_simple_payment() {
        // Проверяем nonce
        if (!wp_verify_nonce($_POST['nonce'], 'simple_payment_nonce')) {
            wp_die('Security check failed');
        }
        
        $token = sanitize_text_field($_POST['token']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        
        // Получаем данные платежа
        $payment_data = $this->get_payment_by_token($token);
        
        if (!$payment_data) {
            wp_send_json_error('Платеж не найден');
        }
        
        // Создаем заказ
        $order_id = $this->create_simple_order($payment_data, $payment_method);
        
        if ($order_id) {
            // Получаем URL для оплаты
            $payment_url = $this->get_payment_url($order_id, $payment_method);
            
            wp_send_json_success(array(
                'order_id' => $order_id,
                'payment_url' => $payment_url,
                'message' => 'Заказ создан успешно'
            ));
        } else {
            wp_send_json_error('Ошибка создания заказа');
        }
    }
    
    /**
     * Создание простого заказа без товара
     */
    public function create_simple_order($payment_data, $payment_method) {
        try {
            // Создаем новый заказ
            $order = wc_create_order();
            
            if (is_wp_error($order)) {
                return false;
            }
            
            // Добавляем произвольную позицию
            $item = new WC_Order_Item_Fee();
            $item->set_name($payment_data->description ?: 'Платеж по ссылке');
            $item->set_amount($payment_data->amount);
            $item->set_total($payment_data->amount);
            $order->add_item($item);
            
            // Устанавливаем данные клиента
            if ($payment_data->customer_email) {
                $order->set_billing_email($payment_data->customer_email);
            }
            if ($payment_data->customer_name) {
                $order->set_billing_first_name($payment_data->customer_name);
            }
            
            // Устанавливаем метод оплаты
            $order->set_payment_method($payment_method);
            $order->set_status('pending');
            
            // Добавляем мета-данные
            $order->update_meta_data('_simple_payment_token', $payment_data->token);
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
     * Получить URL для оплаты
     */
    public function get_payment_url($order_id, $payment_method) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        return $order->get_checkout_payment_url();
    }
    
    /**
     * Создать таблицу простых платежей
     */
    private function create_simple_payments_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'simple_payment_links';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            token varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            description text,
            customer_email varchar(255),
            customer_name varchar(255),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Генерация безопасного токена
     */
    private function generate_secure_token($length = 32) {
        $bytes = random_bytes($length);
        return bin2hex($bytes);
    }
}

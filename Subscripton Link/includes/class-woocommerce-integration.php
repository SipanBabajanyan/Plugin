<?php
/**
 * Класс интеграции с WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class Subscription_Link_WooCommerce_Integration {
    
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
        // Добавляем шорткод для формы оплаты
        add_shortcode('subscription_payment_form', array($this, 'render_payment_form'));
        
        // Обработка AJAX запросов
        add_action('wp_ajax_subscription_payment', array($this, 'handle_payment_ajax'));
        add_action('wp_ajax_nopriv_subscription_payment', array($this, 'handle_payment_ajax'));
        
        // Добавляем кастомные поля к товарам WooCommerce
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_subscription_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_subscription_fields'));
        
        // Добавляем колонку в админку товаров
        add_filter('manage_product_posts_columns', array($this, 'add_subscription_column'));
        add_action('manage_product_posts_custom_column', array($this, 'display_subscription_column'), 10, 2);
    }
    
    /**
     * Рендер формы быстрой оплаты
     */
    public function render_payment_form($atts) {
        $atts = shortcode_atts(array(
            'token' => '',
            'product_id' => 0
        ), $atts);
        
        // Если токен не передан, получаем из URL
        if (empty($atts['token'])) {
            $atts['token'] = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        }
        
        if (empty($atts['token'])) {
            return '<div class="subscription-error">Неверная ссылка для оплаты.</div>';
        }
        
        // Получаем данные подписки
        $subscription_data = $this->get_subscription_by_token($atts['token']);
        
        if (!$subscription_data) {
            return '<div class="subscription-error">Ссылка для оплаты не найдена или неактивна.</div>';
        }
        
        // Получаем данные товара
        $product = wc_get_product($subscription_data->product_id);
        
        if (!$product) {
            return '<div class="subscription-error">Товар не найден.</div>';
        }
        
        // Рендерим форму
        ob_start();
        include SUBSCRIPTION_LINK_PLUGIN_DIR . 'templates/quick-payment.php';
        return ob_get_clean();
    }
    
    /**
     * Получить данные подписки по токену
     */
    public function get_subscription_by_token($token) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND is_active = 1",
            $token
        ));
        
        return $subscription;
    }
    
    /**
     * Обработка AJAX запроса на оплату
     */
    public function handle_payment_ajax() {
        // Проверяем nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subscription_link_nonce')) {
            wp_die('Security check failed');
        }
        
        $token = sanitize_text_field($_POST['token']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        
        // Получаем данные подписки
        $subscription_data = $this->get_subscription_by_token($token);
        
        if (!$subscription_data) {
            wp_send_json_error('Подписка не найдена');
        }
        
        // Создаем заказ
        $order_id = $this->create_order($subscription_data, $payment_method);
        
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
     * Создание заказа в WooCommerce (совместимо с HPOS)
     */
    public function create_order($subscription_data, $payment_method) {
        try {
            // Создаем новый заказ
            $order = wc_create_order();
            
            if (is_wp_error($order)) {
                return false;
            }
            
            // Получаем товар
            $product = wc_get_product($subscription_data->product_id);
            
            if (!$product) {
                return false;
            }
            
            // Добавляем товар в заказ
            $order->add_product($product, 1);
            
            // Устанавливаем адрес доставки (если нужно)
            if ($subscription_data->customer_email) {
                $order->set_billing_email($subscription_data->customer_email);
            }
            if ($subscription_data->customer_name) {
                $order->set_billing_first_name($subscription_data->customer_name);
            }
            
            // Устанавливаем метод оплаты
            $order->set_payment_method($payment_method);
            
            // Устанавливаем статус
            $order->set_status('pending');
            
            // Добавляем мета-данные (совместимо с HPOS)
            $order->update_meta_data('_subscription_token', $subscription_data->token);
            $order->update_meta_data('_subscription_payment', 'yes');
            
            // Рассчитываем налоги и общую сумму
            $order->calculate_totals();
            
            // Сохраняем заказ
            $order->save();
            
            return $order->get_id();
            
        } catch (Exception $e) {
            error_log('Subscription Link Error: ' . $e->getMessage());
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
        
        // Получаем URL оплаты
        $payment_url = $order->get_checkout_payment_url();
        
        // Если это Stripe, добавляем параметры для быстрой оплаты
        if ($payment_method === 'stripe') {
            $payment_url = add_query_arg(array(
                'stripe_payment' => 'true',
                'subscription_payment' => 'true'
            ), $payment_url);
        }
        
        return $payment_url;
    }
    
    /**
     * Добавить поля подписки к товарам
     */
    public function add_subscription_fields() {
        global $post;
        
        echo '<div class="options_group">';
        
        woocommerce_wp_checkbox(array(
            'id' => '_is_subscription_product',
            'label' => __('Товар для подписки', 'subscription-link'),
            'description' => __('Отметьте, если этот товар используется для подписок', 'subscription-link')
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_subscription_amount',
            'label' => __('Сумма подписки', 'subscription-link'),
            'description' => __('Сумма для ежемесячной оплаты (оставьте пустым для использования цены товара)', 'subscription-link'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0'
            )
        ));
        
        echo '</div>';
    }
    
    /**
     * Сохранить поля подписки
     */
    public function save_subscription_fields($post_id) {
        $is_subscription = isset($_POST['_is_subscription_product']) ? 'yes' : 'no';
        update_post_meta($post_id, '_is_subscription_product', $is_subscription);
        
        if (isset($_POST['_subscription_amount'])) {
            update_post_meta($post_id, '_subscription_amount', sanitize_text_field($_POST['_subscription_amount']));
        }
    }
    
    /**
     * Добавить колонку подписки в админку товаров
     */
    public function add_subscription_column($columns) {
        $columns['subscription_link'] = __('Ссылка подписки', 'subscription-link');
        return $columns;
    }
    
    /**
     * Отобразить колонку подписки
     */
    public function display_subscription_column($column, $post_id) {
        if ($column === 'subscription_link') {
            $is_subscription = get_post_meta($post_id, '_is_subscription_product', true);
            
            if ($is_subscription === 'yes') {
                $subscription_manager = Subscription_Link_Manager::get_instance();
                $link = $subscription_manager->get_or_create_subscription_link($post_id);
                
                if ($link) {
                    echo '<a href="' . esc_url($link) . '" target="_blank" class="button button-small">Открыть ссылку</a>';
                } else {
                    echo '<span class="dashicons dashicons-warning" title="Ошибка создания ссылки"></span>';
                }
            } else {
                echo '—';
            }
        }
    }
}

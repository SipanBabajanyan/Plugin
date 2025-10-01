<?php
/**
 * Класс обработки платежей
 */

if (!defined('ABSPATH')) {
    exit;
}

class Subscription_Link_Payment_Handler {
    
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
        // Обработка успешных платежей
        add_action('woocommerce_payment_complete', array($this, 'handle_successful_payment'));
        add_action('woocommerce_order_status_completed', array($this, 'handle_successful_payment'));
        add_action('woocommerce_order_status_processing', array($this, 'handle_successful_payment'));
        
        // Обработка неудачных платежей
        add_action('woocommerce_order_status_failed', array($this, 'handle_failed_payment'));
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_failed_payment'));
        
        // Добавляем мета-данные к заказам
        add_action('woocommerce_checkout_create_order', array($this, 'add_subscription_meta_to_order'));
        
        // Кастомизация процесса оплаты для подписок
        add_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_gateways'));
        
        // Добавляем уведомления
        add_action('wp_footer', array($this, 'add_payment_notifications'));
    }
    
    /**
     * Обработка успешного платежа
     */
    public function handle_successful_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Проверяем, является ли это платежом подписки
        $subscription_token = $order->get_meta('_subscription_token');
        
        if (!$subscription_token) {
            return;
        }
        
        // Логируем успешный платеж
        $this->log_payment($subscription_token, $order_id, 'success');
        
        // Отправляем уведомление клиенту (если настроено)
        $this->send_payment_notification($order, 'success');
        
        // Обновляем статистику подписки
        $this->update_subscription_stats($subscription_token, $order_id);
        
        // Добавляем заметку к заказу
        $order->add_order_note('Платеж подписки обработан успешно. Токен: ' . $subscription_token);
    }
    
    /**
     * Обработка неудачного платежа
     */
    public function handle_failed_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $subscription_token = $order->get_meta('_subscription_token');
        
        if (!$subscription_token) {
            return;
        }
        
        // Логируем неудачный платеж
        $this->log_payment($subscription_token, $order_id, 'failed');
        
        // Отправляем уведомление об ошибке (если настроено)
        $this->send_payment_notification($order, 'failed');
        
        // Добавляем заметку к заказу
        $order->add_order_note('Платеж подписки не удался. Токен: ' . $subscription_token);
    }
    
    /**
     * Добавить мета-данные подписки к заказу (совместимо с HPOS)
     */
    public function add_subscription_meta_to_order($order) {
        // Проверяем, есть ли токен подписки в сессии
        if (isset(WC()->session) && WC()->session->get('subscription_token')) {
            $token = WC()->session->get('subscription_token');
            $order->update_meta_data('_subscription_token', $token);
            $order->update_meta_data('_subscription_payment', 'yes');
        }
    }
    
    /**
     * Фильтрация доступных методов оплаты
     */
    public function filter_payment_gateways($available_gateways) {
        // Если это платеж подписки, можем ограничить методы оплаты
        if (isset(WC()->session) && WC()->session->get('subscription_token')) {
            // Здесь можно добавить логику фильтрации методов оплаты
            // Например, исключить некоторые методы для подписок
        }
        
        return $available_gateways;
    }
    
    /**
     * Логирование платежа
     */
    private function log_payment($token, $order_id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_payments_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'subscription_token' => $token,
                'order_id' => $order_id,
                'status' => $status,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Отправка уведомления о платеже
     */
    private function send_payment_notification($order, $status) {
        $subscription_token = $order->get_meta('_subscription_token');
        
        if (!$subscription_token) {
            return;
        }
        
        // Получаем данные подписки
        $subscription_data = $this->get_subscription_by_token($subscription_token);
        
        if (!$subscription_data || !$subscription_data->customer_email) {
            return;
        }
        
        $subject = $status === 'success' 
            ? 'Платеж подписки успешно обработан' 
            : 'Ошибка обработки платежа подписки';
            
        $message = $this->get_notification_message($order, $status);
        
        // Отправляем email
        wp_mail($subscription_data->customer_email, $subject, $message);
    }
    
    /**
     * Получить сообщение уведомления
     */
    private function get_notification_message($order, $status) {
        $order_id = $order->get_id();
        $order_total = $order->get_formatted_order_total();
        
        if ($status === 'success') {
            $message = "Ваш платеж подписки на сумму {$order_total} успешно обработан.\n\n";
            $message .= "Номер заказа: #{$order_id}\n";
            $message .= "Дата: " . date('d.m.Y H:i') . "\n\n";
            $message .= "Спасибо за использование наших услуг!";
        } else {
            $message = "К сожалению, произошла ошибка при обработке вашего платежа подписки.\n\n";
            $message .= "Номер заказа: #{$order_id}\n";
            $message .= "Сумма: {$order_total}\n";
            $message .= "Дата: " . date('d.m.Y H:i') . "\n\n";
            $message .= "Пожалуйста, попробуйте оплатить еще раз или обратитесь в службу поддержки.";
        }
        
        return $message;
    }
    
    /**
     * Обновление статистики подписки
     */
    private function update_subscription_stats($token, $order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        
        // Увеличиваем счетчик успешных платежей
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET payment_count = payment_count + 1,
                 last_payment_date = NOW(),
                 last_order_id = %d
             WHERE token = %s",
            $order_id,
            $token
        ));
    }
    
    /**
     * Получить данные подписки по токену
     */
    private function get_subscription_by_token($token) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_links';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s",
            $token
        ));
    }
    
    /**
     * Добавить уведомления на страницу
     */
    public function add_payment_notifications() {
        if (!is_page('subscription-payment')) {
            return;
        }
        
        $notifications = array();
        
        // Проверяем статус платежа
        if (isset($_GET['payment_status'])) {
            $status = sanitize_text_field($_GET['payment_status']);
            
            if ($status === 'success') {
                $notifications[] = array(
                    'type' => 'success',
                    'message' => 'Платеж успешно обработан!'
                );
            } elseif ($status === 'failed') {
                $notifications[] = array(
                    'type' => 'error',
                    'message' => 'Произошла ошибка при обработке платежа. Попробуйте еще раз.'
                );
            }
        }
        
        if (!empty($notifications)) {
            echo '<div id="subscription-notifications">';
            foreach ($notifications as $notification) {
                echo '<div class="subscription-notification ' . esc_attr($notification['type']) . '">';
                echo esc_html($notification['message']);
                echo '</div>';
            }
            echo '</div>';
        }
    }
    
    /**
     * Создать таблицу логов платежей
     */
    public function create_payments_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_payments_log';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            subscription_token varchar(255) NOT NULL,
            order_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subscription_token (subscription_token),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

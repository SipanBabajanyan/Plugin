<?php
/**
 * Шаблон страницы быстрой оплаты
 */

if (!defined('ABSPATH')) {
    exit;
}

// Получаем данные подписки
$subscription_manager = Subscription_Link_Manager::get_instance();
$subscription_data = $subscription_manager->get_subscription_by_token($subscription_data->token);

if (!$subscription_data) {
    echo '<div class="subscription-error">Ссылка для оплаты не найдена.</div>';
    return;
}

// Получаем товар
$product = wc_get_product($subscription_data->product_id);
if (!$product) {
    echo '<div class="subscription-error">Товар не найден.</div>';
    return;
}

// Получаем доступные методы оплаты
$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
?>

<div class="subscription-payment-container">
    <div class="subscription-payment-header">
        <h1><?php echo esc_html($product->get_name()); ?></h1>
        <p class="subscription-description">Ежемесячная подписка</p>
    </div>

    <div class="subscription-payment-content">
        <div class="subscription-details">
            <div class="subscription-amount">
                <span class="amount"><?php echo wc_price($subscription_data->amount); ?></span>
                <span class="currency"><?php echo esc_html($subscription_data->currency); ?></span>
            </div>
            
            <?php if ($subscription_data->customer_name): ?>
                <div class="customer-info">
                    <p><strong>Клиент:</strong> <?php echo esc_html($subscription_data->customer_name); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="payment-form">
            <form id="subscription-payment-form" method="post">
                <?php wp_nonce_field('subscription_link_nonce', 'subscription_nonce'); ?>
                <input type="hidden" name="token" value="<?php echo esc_attr($subscription_data->token); ?>">
                
                <div class="payment-methods">
                    <h3>Выберите способ оплаты:</h3>
                    
                    <?php if (empty($available_gateways)): ?>
                        <p class="no-payment-methods">Нет доступных методов оплаты.</p>
                    <?php else: ?>
                        <div class="payment-gateways">
                            <?php foreach ($available_gateways as $gateway): ?>
                                <div class="payment-gateway">
                                    <input type="radio" 
                                           name="payment_method" 
                                           value="<?php echo esc_attr($gateway->id); ?>" 
                                           id="gateway_<?php echo esc_attr($gateway->id); ?>"
                                           required>
                                    <label for="gateway_<?php echo esc_attr($gateway->id); ?>">
                                        <span class="gateway-title"><?php echo esc_html($gateway->get_title()); ?></span>
                                        <?php if ($gateway->get_description()): ?>
                                            <span class="gateway-description"><?php echo esc_html($gateway->get_description()); ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="payment-actions">
                    <button type="submit" class="payment-button" id="process-payment">
                        <span class="button-text">Оплатить сейчас</span>
                        <span class="button-loading" style="display: none;">
                            <span class="spinner"></span>
                            Обработка...
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <div class="subscription-info">
            <div class="info-box">
                <h4>Информация о подписке</h4>
                <ul>
                    <li><strong>Товар:</strong> <?php echo esc_html($product->get_name()); ?></li>
                    <li><strong>Сумма:</strong> <?php echo wc_price($subscription_data->amount); ?></li>
                    <li><strong>Период:</strong> Ежемесячно</li>
                    <li><strong>Создана:</strong> <?php echo date('d.m.Y', strtotime($subscription_data->created_at)); ?></li>
                </ul>
            </div>
            
            <div class="security-info">
                <p><span class="dashicons dashicons-shield"></span> Безопасная оплата через WooCommerce</p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#subscription-payment-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $('#process-payment');
        var $buttonText = $button.find('.button-text');
        var $buttonLoading = $button.find('.button-loading');
        
        // Показываем индикатор загрузки
        $buttonText.hide();
        $buttonLoading.show();
        $button.prop('disabled', true);
        
        // Отправляем AJAX запрос
        $.ajax({
            url: subscriptionLinkAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'subscription_payment',
                token: $form.find('input[name="token"]').val(),
                payment_method: $form.find('input[name="payment_method"]:checked').val(),
                nonce: subscriptionLinkAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Перенаправляем на страницу оплаты
                    window.location.href = response.data.payment_url;
                } else {
                    alert(response.data || subscriptionLinkAjax.messages.error);
                    resetButton();
                }
            },
            error: function() {
                alert(subscriptionLinkAjax.messages.error);
                resetButton();
            }
        });
        
        function resetButton() {
            $buttonText.show();
            $buttonLoading.hide();
            $button.prop('disabled', false);
        }
    });
});
</script>

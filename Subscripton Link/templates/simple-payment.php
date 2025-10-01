<?php
/**
 * Шаблон простой оплаты без товара
 */

if (!defined('ABSPATH')) {
    exit;
}

// Получаем доступные методы оплаты
$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
?>

<div class="simple-payment-container">
    <div class="simple-payment-header">
        <h1>Оплата по ссылке</h1>
        <p class="payment-description"><?php echo esc_html($payment_data->description ?: 'Платеж по ссылке'); ?></p>
    </div>

    <div class="simple-payment-content">
        <div class="payment-details">
            <div class="payment-amount">
                <span class="amount"><?php echo wc_price($payment_data->amount); ?></span>
                <span class="currency"><?php echo esc_html($payment_data->currency); ?></span>
            </div>
            
            <?php if ($payment_data->customer_name): ?>
                <div class="customer-info">
                    <p><strong>Клиент:</strong> <?php echo esc_html($payment_data->customer_name); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="payment-form">
            <form id="simple-payment-form" method="post">
                <?php wp_nonce_field('simple_payment_nonce', 'simple_payment_nonce'); ?>
                <input type="hidden" name="token" value="<?php echo esc_attr($payment_data->token); ?>">
                
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

        <div class="payment-info">
            <div class="info-box">
                <h4>Информация о платеже</h4>
                <ul>
                    <li><strong>Сумма:</strong> <?php echo wc_price($payment_data->amount); ?></li>
                    <li><strong>Описание:</strong> <?php echo esc_html($payment_data->description ?: 'Платеж по ссылке'); ?></li>
                    <li><strong>Создан:</strong> <?php echo date('d.m.Y', strtotime($payment_data->created_at)); ?></li>
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
    $('#simple-payment-form').on('submit', function(e) {
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
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'process_simple_payment',
                token: $form.find('input[name="token"]').val(),
                payment_method: $form.find('input[name="payment_method"]:checked').val(),
                nonce: '<?php echo wp_create_nonce('simple_payment_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Перенаправляем на страницу оплаты
                    window.location.href = response.data.payment_url;
                } else {
                    alert(response.data || 'Произошла ошибка. Попробуйте еще раз.');
                    resetButton();
                }
            },
            error: function() {
                alert('Произошла ошибка. Попробуйте еще раз.');
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

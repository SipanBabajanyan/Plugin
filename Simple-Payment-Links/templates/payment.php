<?php
/**
 * Шаблон страницы оплаты
 */

if (!defined('ABSPATH')) {
    exit;
}

// Получаем доступные методы оплаты
$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
?>

<div class="spl-payment-container">
    <div class="spl-payment-header">
        <h1>Оплата по ссылке</h1>
        <p class="spl-description"><?php echo esc_html($link_data->description ?: 'Платеж по ссылке'); ?></p>
    </div>

    <div class="spl-payment-content">
        <div class="spl-payment-details">
            <div class="spl-amount">
                <span class="amount"><?php echo wc_price($link_data->amount); ?></span>
            </div>
            
            <?php if ($link_data->customer_name): ?>
                <div class="spl-customer-info">
                    <p><strong>Клиент:</strong> <?php echo esc_html($link_data->customer_name); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="spl-payment-form">
            <form id="spl-payment-form" method="post">
                <input type="hidden" name="token" value="<?php echo esc_attr($link_data->token); ?>">
                
                <div class="spl-payment-methods">
                    <h3>Выберите способ оплаты:</h3>
                    
                    <?php if (empty($available_gateways)): ?>
                        <p class="spl-no-methods">Нет доступных методов оплаты.</p>
                    <?php else: ?>
                        <div class="spl-gateways">
                            <?php foreach ($available_gateways as $gateway): ?>
                                <div class="spl-gateway">
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

                <div class="spl-payment-actions">
                    <button type="submit" class="spl-payment-button" id="spl-process-payment">
                        <span class="button-text">Оплатить сейчас</span>
                        <span class="button-loading" style="display: none;">
                            <span class="spinner"></span>
                            Обработка...
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <div class="spl-payment-info">
            <div class="spl-info-box">
                <h4>Информация о платеже</h4>
                <ul>
                    <li><strong>Сумма:</strong> <?php echo wc_price($link_data->amount); ?></li>
                    <li><strong>Описание:</strong> <?php echo esc_html($link_data->description ?: 'Платеж по ссылке'); ?></li>
                    <li><strong>Создан:</strong> <?php echo esc_html(date('d.m.Y', strtotime($link_data->created_at))); ?></li>
                </ul>
            </div>
            
            <div class="spl-security-info">
                <p><span class="dashicons dashicons-shield"></span> Безопасная оплата через WooCommerce</p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#spl-payment-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $('#spl-process-payment');
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
                action: 'process_payment',
                token: $form.find('input[name="token"]').val(),
                payment_method: $form.find('input[name="payment_method"]:checked').val()
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

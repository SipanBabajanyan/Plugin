<?php
/**
 * Шаблон админ-панели для простых платежей
 */

if (!defined('ABSPATH')) {
    exit;
}

$simple_payment = Subscription_Link_Simple_Payment::get_instance();
?>

<div class="wrap simple-payments-admin">
    <h1 class="wp-heading-inline">Simple Payment Links</h1>
    <a href="#" class="page-title-action" id="create-simple-payment">Create New</a>
    
    <hr class="wp-header-end">

    <!-- Форма создания простой ссылки -->
    <div id="create-simple-payment-form" style="display: none;">
        <div class="simple-payment-form">
            <h2>Create Simple Payment Link</h2>
            
            <form id="simple-payment-create-form">
                <?php wp_nonce_field('simple_payment_nonce', 'simple_payment_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="simple_amount">Amount</label>
                            </th>
                            <td>
                                <input type="number" 
                                       name="amount" 
                                       id="simple_amount" 
                                       step="0.01" 
                                       min="0" 
                                       class="regular-text"
                                       required>
                                <p class="description">Payment amount</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="simple_description">Description</label>
                            </th>
                            <td>
                                <input type="text" 
                                       name="description" 
                                       id="simple_description" 
                                       class="regular-text"
                                       placeholder="Payment description">
                                <p class="description">Description for the payment (optional)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="simple_customer_name">Customer Name</label>
                            </th>
                            <td>
                                <input type="text" 
                                       name="customer_name" 
                                       id="simple_customer_name" 
                                       class="regular-text"
                                       placeholder="Customer name">
                                <p class="description">Customer name (optional)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="simple_customer_email">Customer Email</label>
                            </th>
                            <td>
                                <input type="email" 
                                       name="customer_email" 
                                       id="simple_customer_email" 
                                       class="regular-text"
                                       placeholder="customer@example.com">
                                <p class="description">Customer email for notifications (optional)</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="form-actions">
                    <input type="submit" class="button button-primary" value="Create Payment Link">
                    <button type="button" class="button" id="cancel-create">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Результат создания -->
    <div id="simple-payment-result" style="display: none;">
        <div class="simple-payment-result-content">
            <h3>Payment Link Created Successfully!</h3>
            <div class="payment-link-display">
                <label for="simple-payment-link-url">Payment Link:</label>
                <input type="text" id="simple-payment-link-url" readonly class="large-text">
                <button type="button" id="copy-simple-link" class="button">Copy Link</button>
            </div>
            <div class="payment-actions">
                <a href="#" id="test-simple-link" class="button" target="_blank">Test Link</a>
                <button type="button" class="button button-primary" id="create-another">Create Another</button>
            </div>
        </div>
    </div>

    <!-- Список существующих ссылок -->
    <div id="simple-payments-list">
        <h2>Existing Payment Links</h2>
        <p>Simple payment links will be displayed here. This feature is coming soon!</p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Показать форму создания
    $('#create-simple-payment').on('click', function(e) {
        e.preventDefault();
        $('#create-simple-payment-form').show();
        $('#simple-payment-result').hide();
    });
    
    // Скрыть форму создания
    $('#cancel-create').on('click', function() {
        $('#create-simple-payment-form').hide();
    });
    
    // Обработка формы создания
    $('#simple-payment-create-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('input[type="submit"]');
        var originalText = $submitButton.val();
        
        // Показываем индикатор загрузки
        $submitButton.val('Creating...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'create_simple_payment',
                amount: $('#simple_amount').val(),
                description: $('#simple_description').val(),
                customer_name: $('#simple_customer_name').val(),
                customer_email: $('#simple_customer_email').val(),
                nonce: '<?php echo wp_create_nonce('simple_payment_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Показываем результат
                    $('#simple-payment-link-url').val(response.data.link);
                    $('#test-simple-link').attr('href', response.data.link);
                    $('#simple-payment-result').show();
                    $('#create-simple-payment-form').hide();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while creating the payment link.');
            },
            complete: function() {
                $submitButton.val(originalText).prop('disabled', false);
            }
        });
    });
    
    // Копирование ссылки
    $('#copy-simple-link').on('click', function() {
        var linkInput = $('#simple-payment-link-url');
        var link = linkInput.val();
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(link).then(function() {
                alert('Link copied to clipboard!');
            });
        } else {
            linkInput.select();
            document.execCommand('copy');
            alert('Link copied to clipboard!');
        }
    });
    
    // Создать еще одну ссылку
    $('#create-another').on('click', function() {
        $('#simple-payment-result').hide();
        $('#create-simple-payment-form').show();
        $('#simple-payment-create-form')[0].reset();
    });
});
</script>

<?php
/**
 * Шаблон создания новой подписки
 */

if (!defined('ABSPATH')) {
    exit;
}

$admin_interface = Subscription_Link_Admin_Interface::get_instance();
$products = $admin_interface->get_subscription_products();
?>

<div class="wrap subscription-links-create">
    <h1>Create New Subscription Link</h1>
    
    <div class="subscription-create-form">
        <form id="create-subscription-form" method="post">
            <?php wp_nonce_field('subscription_link_admin_nonce', 'subscription_nonce'); ?>
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="product_id">Product</label>
                        </th>
                        <td>
                            <?php if (empty($products)): ?>
                                <p class="description">
                                    No subscription products found. 
                                    <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>">Create a product</a> 
                                    and mark it as a subscription product first.
                                </p>
                            <?php else: ?>
                                <select name="product_id" id="product_id" required>
                                    <option value="">Select a product...</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo esc_attr($product['id']); ?>" 
                                                data-price="<?php echo esc_attr($product['price']); ?>">
                                            <?php echo esc_html($product['title']); ?> 
                                            (<?php echo wc_price($product['price']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select the product for this subscription.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="subscription_amount">Subscription Amount</label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="subscription_amount" 
                                   id="subscription_amount" 
                                   step="0.01" 
                                   min="0" 
                                   class="regular-text">
                            <p class="description">Leave empty to use product price. Amount will be auto-filled when you select a product.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="customer_name">Customer Name</label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="customer_name" 
                                   id="customer_name" 
                                   class="regular-text"
                                   placeholder="Optional">
                            <p class="description">Customer name for this subscription (optional).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="customer_email">Customer Email</label>
                        </th>
                        <td>
                            <input type="email" 
                                   name="customer_email" 
                                   id="customer_email" 
                                   class="regular-text"
                                   placeholder="customer@example.com">
                            <p class="description">Customer email for notifications (optional).</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div class="form-actions">
                <input type="submit" class="button button-primary" value="Create Subscription Link">
                <a href="<?php echo admin_url('admin.php?page=subscription-links'); ?>" class="button">Cancel</a>
            </div>
        </form>
    </div>
    
    <div id="subscription-result" style="display: none;">
        <div class="subscription-result-content">
            <h3>Subscription Link Created Successfully!</h3>
            <div class="subscription-link-display">
                <label for="subscription-link-url">Subscription Link:</label>
                <input type="text" id="subscription-link-url" readonly class="large-text">
                <button type="button" id="copy-link" class="button">Copy Link</button>
            </div>
            <div class="subscription-actions">
                <a href="#" id="test-link" class="button" target="_blank">Test Link</a>
                <a href="<?php echo admin_url('admin.php?page=subscription-links'); ?>" class="button button-primary">View All Subscriptions</a>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Автозаполнение суммы при выборе товара
    $('#product_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var price = selectedOption.data('price');
        
        if (price) {
            $('#subscription_amount').val(price);
        }
    });
    
    // Обработка формы
    $('#create-subscription-form').on('submit', function(e) {
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
                action: 'create_subscription_link',
                product_id: $('#product_id').val(),
                subscription_amount: $('#subscription_amount').val(),
                customer_name: $('#customer_name').val(),
                customer_email: $('#customer_email').val(),
                nonce: '<?php echo wp_create_nonce('subscription_link_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Показываем результат
                    $('#subscription-link-url').val(response.data.link);
                    $('#test-link').attr('href', response.data.link);
                    $('#subscription-result').show();
                    $form.hide();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while creating the subscription link.');
            },
            complete: function() {
                $submitButton.val(originalText).prop('disabled', false);
            }
        });
    });
    
    // Копирование ссылки
    $('#copy-link').on('click', function() {
        var linkInput = $('#subscription-link-url');
        linkInput.select();
        document.execCommand('copy');
        
        var $button = $(this);
        var originalText = $button.text();
        $button.text('Copied!');
        
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
});
</script>

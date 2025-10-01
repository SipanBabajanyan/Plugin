<?php
/**
 * Шаблон страницы настроек
 */

if (!defined('ABSPATH')) {
    exit;
}

$options = get_option('subscription_link_options', array());
?>

<div class="wrap subscription-links-settings">
    <h1>Subscription Links Settings</h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('subscription_link_settings'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="default_currency">Default Currency</label>
                    </th>
                    <td>
                        <select name="subscription_link_options[default_currency]" id="default_currency">
                            <?php
                            $currencies = array(
                                'USD' => 'US Dollar ($)',
                                'EUR' => 'Euro (€)',
                                'RUB' => 'Russian Ruble (₽)',
                                'UAH' => 'Ukrainian Hryvnia (₴)',
                                'GBP' => 'British Pound (£)',
                                'CAD' => 'Canadian Dollar (C$)',
                                'AUD' => 'Australian Dollar (A$)'
                            );
                            
                            $selected_currency = isset($options['default_currency']) ? $options['default_currency'] : get_woocommerce_currency();
                            
                            foreach ($currencies as $code => $name) {
                                $selected = selected($selected_currency, $code, false);
                                echo '<option value="' . esc_attr($code) . '"' . $selected . '>' . esc_html($name) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Default currency for new subscription links.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Email Notifications</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" 
                                       name="subscription_link_options[email_notifications]" 
                                       value="1" 
                                       <?php checked(isset($options['email_notifications']) ? $options['email_notifications'] : 1, 1); ?>>
                                Enable email notifications for payments
                            </label>
                            <p class="description">Send email notifications to customers when payments are processed.</p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="admin_email">Admin Email</label>
                    </th>
                    <td>
                        <input type="email" 
                               name="subscription_link_options[admin_email]" 
                               id="admin_email" 
                               value="<?php echo esc_attr(isset($options['admin_email']) ? $options['admin_email'] : get_option('admin_email')); ?>"
                               class="regular-text">
                        <p class="description">Email address for admin notifications about subscription payments.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="payment_page_title">Payment Page Title</label>
                    </th>
                    <td>
                        <input type="text" 
                               name="subscription_link_options[payment_page_title]" 
                               id="payment_page_title" 
                               value="<?php echo esc_attr(isset($options['payment_page_title']) ? $options['payment_page_title'] : 'Subscription Payment'); ?>"
                               class="regular-text">
                        <p class="description">Title displayed on the payment page.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="success_redirect_url">Success Redirect URL</label>
                    </th>
                    <td>
                        <input type="url" 
                               name="subscription_link_options[success_redirect_url]" 
                               id="success_redirect_url" 
                               value="<?php echo esc_attr(isset($options['success_redirect_url']) ? $options['success_redirect_url'] : ''); ?>"
                               class="regular-text"
                               placeholder="https://yoursite.com/thank-you">
                        <p class="description">URL to redirect customers after successful payment (optional).</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="failed_redirect_url">Failed Payment Redirect URL</label>
                    </th>
                    <td>
                        <input type="url" 
                               name="subscription_link_options[failed_redirect_url]" 
                               id="failed_redirect_url" 
                               value="<?php echo esc_attr(isset($options['failed_redirect_url']) ? $options['failed_redirect_url'] : ''); ?>"
                               class="regular-text"
                               placeholder="https://yoursite.com/payment-failed">
                        <p class="description">URL to redirect customers after failed payment (optional).</p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div class="settings-actions">
            <?php submit_button('Save Settings'); ?>
        </div>
    </form>
    
    <div class="settings-info">
        <h3>Plugin Information</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">Version</th>
                    <td><?php echo SUBSCRIPTION_LINK_VERSION; ?></td>
                </tr>
                <tr>
                    <th scope="row">WooCommerce Version</th>
                    <td><?php echo WC()->version; ?></td>
                </tr>
                <tr>
                    <th scope="row">WordPress Version</th>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
                <tr>
                    <th scope="row">PHP Version</th>
                    <td><?php echo PHP_VERSION; ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="settings-help">
        <h3>How to Use</h3>
        <ol>
            <li>Create a product in WooCommerce and mark it as a subscription product</li>
            <li>Go to "Subscription Links" → "Create New" to generate a subscription link</li>
            <li>Share the generated link with your customers</li>
            <li>Customers can use the same link every month to make payments</li>
            <li>Monitor payments and subscription status in the admin dashboard</li>
        </ol>
        
        <h4>Shortcode Usage</h4>
        <p>You can also use the shortcode to display payment forms on any page:</p>
        <code>[subscription_payment_form token="your_token_here"]</code>
        
        <h4>API Usage</h4>
        <p>Create subscription links programmatically:</p>
        <pre><code>$subscription_manager = Subscription_Link_Manager::get_instance();
$link = $subscription_manager->get_or_create_subscription_link($product_id, $email, $name);</code></pre>
    </div>
</div>

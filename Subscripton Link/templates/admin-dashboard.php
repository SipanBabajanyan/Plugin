<?php
/**
 * Шаблон админ-панели
 */

if (!defined('ABSPATH')) {
    exit;
}

$admin_interface = Subscription_Link_Admin_Interface::get_instance();
$stats = $admin_interface->get_stats();
?>

<div class="wrap subscription-links-admin">
    <h1 class="wp-heading-inline">Subscription Links</h1>
    <a href="<?php echo admin_url('admin.php?page=subscription-links-create'); ?>" class="page-title-action">Add New</a>
    
    <hr class="wp-header-end">

    <!-- Статистика -->
    <div class="subscription-stats">
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-number"><?php echo esc_html($stats['total_subscriptions']); ?></div>
                <div class="stat-label">Total Subscriptions</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo esc_html($stats['active_subscriptions']); ?></div>
                <div class="stat-label">Active Subscriptions</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo esc_html($stats['total_payments']); ?></div>
                <div class="stat-label">Total Payments</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo esc_html($stats['successful_payments']); ?></div>
                <div class="stat-label">Successful Payments</div>
            </div>
        </div>
    </div>

    <!-- Фильтры и поиск -->
    <div class="subscription-filters">
        <form method="get" class="search-form">
            <input type="hidden" name="page" value="subscription-links">
            <input type="search" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>" placeholder="Search subscriptions...">
            <input type="submit" class="button" value="Search">
        </form>
    </div>

    <!-- Таблица подписок -->
    <form method="post" id="subscriptions-form">
        <?php wp_nonce_field('subscription_link_bulk_action'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action">
                    <option value="">Bulk Actions</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="delete">Delete</option>
                </select>
                <input type="submit" class="button action" value="Apply">
            </div>
            
            <div class="tablenav-pages">
                <?php
                $pagination = paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => ceil($total_subscriptions / 20),
                    'current' => $page
                ));
                echo $pagination;
                ?>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th class="manage-column column-token">Token</th>
                    <th class="manage-column column-product">Product</th>
                    <th class="manage-column column-amount">Amount</th>
                    <th class="manage-column column-customer">Customer</th>
                    <th class="manage-column column-created">Created</th>
                    <th class="manage-column column-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscriptions)): ?>
                    <tr>
                        <td colspan="7" class="no-items">No subscriptions found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subscriptions as $subscription): ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="subscription_tokens[]" value="<?php echo esc_attr($subscription->token); ?>">
                            </th>
                            <td class="column-token">
                                <code><?php echo esc_html(substr($subscription->token, 0, 8) . '...'); ?></code>
                            </td>
                            <td class="column-product">
                                <strong><?php echo esc_html($subscription->product_name); ?></strong>
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="<?php echo esc_url(get_edit_post_link($subscription->product_id)); ?>">Edit Product</a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-amount">
                                <?php echo wc_price($subscription->amount); ?>
                            </td>
                            <td class="column-customer">
                                <?php if ($subscription->customer_name): ?>
                                    <strong><?php echo esc_html($subscription->customer_name); ?></strong><br>
                                <?php endif; ?>
                                <?php if ($subscription->customer_email): ?>
                                    <a href="mailto:<?php echo esc_attr($subscription->customer_email); ?>">
                                        <?php echo esc_html($subscription->customer_email); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="column-created">
                                <?php echo esc_html(date('M j, Y', strtotime($subscription->created_at))); ?>
                            </td>
                            <td class="column-actions">
                                <?php
                                $payment_url = home_url('/subscription-payment/?token=' . $subscription->token);
                                ?>
                                <a href="<?php echo esc_url($payment_url); ?>" target="_blank" class="button button-small">View Link</a>
                                <button type="button" class="button button-small deactivate-link" data-token="<?php echo esc_attr($subscription->token); ?>">Deactivate</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action2">
                    <option value="">Bulk Actions</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="delete">Delete</option>
                </select>
                <input type="submit" class="button action" value="Apply">
            </div>
            
            <div class="tablenav-pages">
                <?php echo $pagination; ?>
            </div>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Выбор всех чекбоксов
    $('#cb-select-all').on('change', function() {
        $('input[name="subscription_tokens[]"]').prop('checked', this.checked);
    });
    
    // Деактивация ссылки
    $('.deactivate-link').on('click', function() {
        if (!confirm('Are you sure you want to deactivate this subscription link?')) {
            return;
        }
        
        var token = $(this).data('token');
        var $button = $(this);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'deactivate_subscription_link',
                token: token,
                nonce: '<?php echo wp_create_nonce('subscription_link_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.closest('tr').fadeOut();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred');
            }
        });
    });
});
</script>

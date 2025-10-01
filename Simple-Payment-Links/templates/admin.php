<?php
/**
 * Шаблон админки
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Simple Payment Links</h1>
    
    <!-- Форма создания ссылки -->
    <div class="spl-create-form">
        <h2>Создать новую ссылку</h2>
        
        <form method="post" id="create-payment-link-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="amount">Сумма *</label>
                    </th>
                    <td>
                        <input type="number" 
                               name="amount" 
                               id="amount" 
                               step="0.01" 
                               min="0" 
                               class="regular-text"
                               required>
                        <p class="description">Сумма для оплаты</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="title">Название *</label>
                    </th>
                    <td>
                        <input type="text" 
                               name="title" 
                               id="title" 
                               class="regular-text"
                               placeholder="Название платежа"
                               required>
                        <p class="description">Название платежа (обязательно)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="description">Описание</label>
                    </th>
                    <td>
                        <input type="text" 
                               name="description" 
                               id="description" 
                               class="regular-text"
                               placeholder="Дополнительное описание">
                        <p class="description">Дополнительное описание (необязательно)</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="create_link" class="button button-primary" value="Создать ссылку">
            </p>
        </form>
    </div>
    
    <!-- Список существующих ссылок -->
    <div class="spl-links-list">
        <h2>Существующие ссылки</h2>
        
        <?php if (empty($links)): ?>
            <p>Ссылки не найдены.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Сумма</th>
                        <th>Название</th>
                        <th>ID заказа</th>
                        <th>Статус</th>
                        <th>Создана</th>
                        <th>Ссылка</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($links as $link): ?>
                        <tr>
                            <td><?php echo wc_price($link->amount); ?></td>
                            <td><?php echo esc_html($link->description ?: '—'); ?></td>
                            <td>
                                <?php if (isset($link->order_id) && $link->order_id): ?>
                                    <a href="<?php echo admin_url('post.php?post=' . $link->order_id . '&action=edit'); ?>" target="_blank">
                                        #<?php echo esc_html($link->order_id); ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-<?php echo esc_attr($link->status); ?>">
                                    <?php echo esc_html(ucfirst($link->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('d.m.Y H:i', strtotime($link->created_at))); ?></td>
                            <td>
                                <?php
                                if (isset($link->order_id) && $link->order_id) {
                                    $order = wc_get_order($link->order_id);
                                    $payment_url = $order ? $order->get_checkout_payment_url() : '#';
                                } else {
                                    $payment_url = home_url('/payment/?token=' . $link->token);
                                }
                                ?>
                                <input type="text" 
                                       value="<?php echo esc_url($payment_url); ?>" 
                                       readonly 
                                       class="large-text"
                                       onclick="this.select();">
                                <br>
                                <a href="<?php echo esc_url($payment_url); ?>" 
                                   target="_blank" 
                                   class="button button-small">Открыть</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
.spl-create-form {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.spl-links-list {
    margin-top: 30px;
}

.status-active {
    color: #46b450;
    font-weight: bold;
}

.status-inactive {
    color: #dc3232;
    font-weight: bold;
}

.wp-list-table input[readonly] {
    background: #f1f1f1;
    cursor: pointer;
}
</style>

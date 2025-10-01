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
                        <label for="amount">Сумма</label>
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
                        <label for="description">Описание</label>
                    </th>
                    <td>
                        <input type="text" 
                               name="description" 
                               id="description" 
                               class="regular-text"
                               placeholder="Описание платежа">
                        <p class="description">Описание платежа (необязательно)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="customer_name">Имя клиента</label>
                    </th>
                    <td>
                        <input type="text" 
                               name="customer_name" 
                               id="customer_name" 
                               class="regular-text"
                               placeholder="Имя клиента">
                        <p class="description">Имя клиента (необязательно)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="customer_email">Email клиента</label>
                    </th>
                    <td>
                        <input type="email" 
                               name="customer_email" 
                               id="customer_email" 
                               class="regular-text"
                               placeholder="email@example.com">
                        <p class="description">Email клиента (необязательно)</p>
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
                        <th>Описание</th>
                        <th>Клиент</th>
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
                                <?php if ($link->customer_name): ?>
                                    <strong><?php echo esc_html($link->customer_name); ?></strong><br>
                                <?php endif; ?>
                                <?php if ($link->customer_email): ?>
                                    <a href="mailto:<?php echo esc_attr($link->customer_email); ?>">
                                        <?php echo esc_html($link->customer_email); ?>
                                    </a>
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
                                $payment_url = home_url('/payment/?token=' . $link->token);
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

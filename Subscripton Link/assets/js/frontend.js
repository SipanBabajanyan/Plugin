/**
 * JavaScript для фронтенда
 */

(function($) {
    'use strict';

    // Инициализация при загрузке страницы
    $(document).ready(function() {
        initSubscriptionPayment();
    });

    /**
     * Инициализация формы оплаты подписки
     */
    function initSubscriptionPayment() {
        // Обработка отправки формы
        $('#subscription-payment-form').on('submit', handlePaymentSubmit);
        
        // Обработка выбора метода оплаты
        $('input[name="payment_method"]').on('change', handlePaymentMethodChange);
        
        // Инициализация уведомлений
        initNotifications();
    }

    /**
     * Обработка отправки формы оплаты
     */
    function handlePaymentSubmit(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $('#process-payment');
        var $buttonText = $button.find('.button-text');
        var $buttonLoading = $button.find('.button-loading');
        
        // Валидация формы
        if (!validatePaymentForm($form)) {
            return;
        }
        
        // Показываем индикатор загрузки
        showLoadingState($button, $buttonText, $buttonLoading);
        
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
                    handlePaymentSuccess(response.data);
                } else {
                    handlePaymentError(response.data || subscriptionLinkAjax.messages.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Payment error:', error);
                handlePaymentError(subscriptionLinkAjax.messages.error);
            },
            complete: function() {
                hideLoadingState($button, $buttonText, $buttonLoading);
            }
        });
    }

    /**
     * Валидация формы оплаты
     */
    function validatePaymentForm($form) {
        var selectedMethod = $form.find('input[name="payment_method"]:checked');
        
        if (selectedMethod.length === 0) {
            showNotification('Пожалуйста, выберите способ оплаты', 'error');
            return false;
        }
        
        return true;
    }

    /**
     * Обработка успешного платежа
     */
    function handlePaymentSuccess(data) {
        showNotification(subscriptionLinkAjax.messages.success, 'success');
        
        // Перенаправляем на страницу оплаты через небольшую задержку
        setTimeout(function() {
            if (data.payment_url) {
                window.location.href = data.payment_url;
            }
        }, 1500);
    }

    /**
     * Обработка ошибки платежа
     */
    function handlePaymentError(message) {
        showNotification(message, 'error');
    }

    /**
     * Обработка изменения метода оплаты
     */
    function handlePaymentMethodChange() {
        var $selectedMethod = $(this);
        var $label = $selectedMethod.next('label');
        
        // Убираем выделение с других методов
        $('input[name="payment_method"]').not(this).next('label').removeClass('selected');
        
        // Выделяем выбранный метод
        $label.addClass('selected');
        
        // Обновляем кнопку оплаты
        updatePaymentButton($selectedMethod);
    }

    /**
     * Обновление кнопки оплаты
     */
    function updatePaymentButton($selectedMethod) {
        var $button = $('#process-payment');
        var gatewayTitle = $selectedMethod.next('label').find('.gateway-title').text();
        
        $button.find('.button-text').text('Оплатить через ' + gatewayTitle);
    }

    /**
     * Показать состояние загрузки
     */
    function showLoadingState($button, $buttonText, $buttonLoading) {
        $buttonText.hide();
        $buttonLoading.show();
        $button.prop('disabled', true).addClass('loading');
    }

    /**
     * Скрыть состояние загрузки
     */
    function hideLoadingState($button, $buttonText, $buttonLoading) {
        $buttonText.show();
        $buttonLoading.hide();
        $button.prop('disabled', false).removeClass('loading');
    }

    /**
     * Показать уведомление
     */
    function showNotification(message, type) {
        // Удаляем существующие уведомления
        $('.subscription-notification').remove();
        
        // Создаем новое уведомление
        var $notification = $('<div class="subscription-notification ' + type + '">' + message + '</div>');
        
        // Добавляем уведомление в контейнер
        $('.subscription-payment-container').prepend($notification);
        
        // Автоматически скрываем через 5 секунд
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Инициализация уведомлений
     */
    function initNotifications() {
        // Показываем уведомления из URL параметров
        var urlParams = new URLSearchParams(window.location.search);
        var paymentStatus = urlParams.get('payment_status');
        
        if (paymentStatus === 'success') {
            showNotification('Платеж успешно обработан!', 'success');
        } else if (paymentStatus === 'failed') {
            showNotification('Произошла ошибка при обработке платежа. Попробуйте еще раз.', 'error');
        }
    }

    /**
     * Копирование ссылки в буфер обмена
     */
    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                showNotification('Ссылка скопирована в буфер обмена', 'success');
            });
        } else {
            // Fallback для старых браузеров
            var textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showNotification('Ссылка скопирована в буфер обмена', 'success');
        }
    }

    // Экспортируем функции для глобального использования
    window.SubscriptionPayment = {
        showNotification: showNotification,
        copyToClipboard: copyToClipboard
    };

})(jQuery);

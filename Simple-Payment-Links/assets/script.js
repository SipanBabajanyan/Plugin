/**
 * JavaScript для Simple Payment Links
 */

(function($) {
    'use strict';

    // Инициализация при загрузке страницы
    $(document).ready(function() {
        initPaymentForm();
    });

    /**
     * Инициализация формы оплаты
     */
    function initPaymentForm() {
        // Обработка отправки формы
        $('#spl-payment-form').on('submit', handlePaymentSubmit);
        
        // Обработка выбора метода оплаты
        $('input[name="payment_method"]').on('change', handlePaymentMethodChange);
    }

    /**
     * Обработка отправки формы оплаты
     */
    function handlePaymentSubmit(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $('#spl-process-payment');
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
            url: splAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'process_payment',
                token: $form.find('input[name="token"]').val(),
                payment_method: $form.find('input[name="payment_method"]:checked').val()
            },
            success: function(response) {
                if (response.success) {
                    handlePaymentSuccess(response.data);
                } else {
                    handlePaymentError(response.data || 'Произошла ошибка. Попробуйте еще раз.');
                }
            },
            error: function() {
                handlePaymentError('Произошла ошибка. Попробуйте еще раз.');
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
            alert('Пожалуйста, выберите способ оплаты');
            return false;
        }
        
        return true;
    }

    /**
     * Обработка успешного платежа
     */
    function handlePaymentSuccess(data) {
        alert('Платеж успешно обработан!');
        
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
        alert(message);
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

})(jQuery);

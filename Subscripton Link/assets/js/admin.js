/**
 * JavaScript для админ-панели - Simple Payment Links
 */

(function($) {
    'use strict';

    // Инициализация при загрузке страницы
    $(document).ready(function() {
        initAdminInterface();
    });

    /**
     * Инициализация админ-интерфейса
     */
    function initAdminInterface() {
        // Инициализация страницы простых платежей
        if ($('.simple-payments-admin').length) {
            initSimplePaymentsPage();
        }
        
        // Инициализация страницы настроек
        if ($('.subscription-links-settings').length) {
            initSettingsPage();
        }
    }

    /**
     * Инициализация страницы простых платежей
     */
    function initSimplePaymentsPage() {
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
        $('#simple-payment-create-form').on('submit', handleSimpleCreateForm);
        
        // Копирование ссылки
        $('#copy-simple-link').on('click', handleCopyLink);
        
        // Создать еще одну ссылку
        $('#create-another').on('click', function() {
            $('#simple-payment-result').hide();
            $('#create-simple-payment-form').show();
            $('#simple-payment-create-form')[0].reset();
        });
    }

    /**
     * Инициализация страницы настроек
     */
    function initSettingsPage() {
        // Валидация URL полей
        $('input[type="url"]').on('blur', validateUrl);
        
        // Обработка сохранения настроек
        $('form').on('submit', handleSettingsSave);
    }

    /**
     * Обработка формы создания простых платежей
     */
    function handleSimpleCreateForm(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('input[type="submit"]');
        var originalText = $submitButton.val();
        
        // Валидация формы
        if (!validateSimpleCreateForm($form)) {
            return;
        }
        
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
                nonce: $('#simple-payment-create-form input[name="simple_payment_nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    handleSimpleCreateSuccess(response.data);
                } else {
                    showAdminNotice('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                showAdminNotice('An error occurred while creating the payment link.', 'error');
            },
            complete: function() {
                $submitButton.val(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Валидация формы создания простых платежей
     */
    function validateSimpleCreateForm($form) {
        var amount = $('#simple_amount').val();
        var email = $('#simple_customer_email').val();
        
        if (!amount || parseFloat(amount) <= 0) {
            showAdminNotice('Please enter a valid amount', 'error');
            return false;
        }
        
        if (email && !isValidEmail(email)) {
            showAdminNotice('Please enter a valid email', 'error');
            return false;
        }
        
        return true;
    }

    /**
     * Обработка успешного создания
     */
    function handleSimpleCreateSuccess(data) {
        // Показываем результат
        $('#simple-payment-link-url').val(data.link);
        $('#test-simple-link').attr('href', data.link);
        $('#simple-payment-result').show();
        $('#create-simple-payment-form').hide();
        
        showAdminNotice('Payment link created successfully!', 'success');
    }

    /**
     * Обработка копирования ссылки
     */
    function handleCopyLink(e) {
        e.preventDefault();
        
        var linkInput = $('#simple-payment-link-url');
        var link = linkInput.val();
        
        if (!link) {
            showAdminNotice('No link to copy', 'error');
            return;
        }
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(link).then(function() {
                showAdminNotice('Link copied to clipboard!', 'success');
            });
        } else {
            linkInput.select();
            document.execCommand('copy');
            showAdminNotice('Link copied to clipboard!', 'success');
        }
    }

    /**
     * Валидация URL
     */
    function validateUrl() {
        var $input = $(this);
        var url = $input.val().trim();
        
        if (url && !isValidUrl(url)) {
            $input.addClass('error');
            showAdminNotice('Please enter a valid URL', 'error');
        } else {
            $input.removeClass('error');
        }
    }

    /**
     * Обработка сохранения настроек
     */
    function handleSettingsSave() {
        // Валидация всех URL полей
        var hasErrors = false;
        
        $('input[type="url"]').each(function() {
            var $input = $(this);
            var url = $input.val().trim();
            
            if (url && !isValidUrl(url)) {
                $input.addClass('error');
                hasErrors = true;
            } else {
                $input.removeClass('error');
            }
        });
        
        if (hasErrors) {
            showAdminNotice('Please fix URL field errors', 'error');
            return false;
        }
    }

    /**
     * Показать уведомление в админке
     */
    function showAdminNotice(message, type) {
        // Удаляем существующие уведомления
        $('.simple-admin-notice').remove();
        
        // Создаем новое уведомление
        var $notice = $('<div class="notice simple-admin-notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Добавляем уведомление в начало страницы
        $('.wrap h1').after($notice);
        
        // Автоматически скрываем через 5 секунд
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Валидация email
     */
    function isValidEmail(email) {
        var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    /**
     * Валидация URL
     */
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }

    // Экспортируем функции для глобального использования
    window.SimplePaymentAdmin = {
        showNotice: showAdminNotice,
        validateEmail: isValidEmail,
        validateUrl: isValidUrl
    };

})(jQuery);
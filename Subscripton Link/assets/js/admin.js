/**
 * JavaScript для админ-панели
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
        // Инициализация страницы подписок
        if ($('.subscription-links-admin').length) {
            initSubscriptionsPage();
        }
        
        // Инициализация страницы создания
        if ($('.subscription-links-create').length) {
            initCreatePage();
        }
        
        // Инициализация страницы настроек
        if ($('.subscription-links-settings').length) {
            initSettingsPage();
        }
    }

    /**
     * Инициализация страницы подписок
     */
    function initSubscriptionsPage() {
        // Обработка выбора всех чекбоксов
        $('#cb-select-all').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('input[name="subscription_tokens[]"]').prop('checked', isChecked);
        });
        
        // Обработка деактивации ссылки
        $('.deactivate-link').on('click', handleDeactivateLink);
        
        // Обработка массовых действий
        $('#subscriptions-form').on('submit', handleBulkAction);
        
        // Обработка поиска
        $('.search-form').on('submit', handleSearch);
    }

    /**
     * Инициализация страницы создания
     */
    function initCreatePage() {
        // Автозаполнение суммы при выборе товара
        $('#product_id').on('change', handleProductChange);
        
        // Обработка формы создания
        $('#create-subscription-form').on('submit', handleCreateForm);
        
        // Копирование ссылки
        $('#copy-link').on('click', handleCopyLink);
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
     * Обработка деактивации ссылки
     */
    function handleDeactivateLink(e) {
        e.preventDefault();
        
        if (!confirm('Вы уверены, что хотите деактивировать эту ссылку подписки?')) {
            return;
        }
        
        var $button = $(this);
        var token = $button.data('token');
        var $row = $button.closest('tr');
        
        // Показываем индикатор загрузки
        $button.prop('disabled', true).text('Деактивация...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'deactivate_subscription_link',
                token: token,
                nonce: subscriptionLinkAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                    });
                    showAdminNotice('Ссылка подписки деактивирована', 'success');
                } else {
                    showAdminNotice('Ошибка: ' + response.data, 'error');
                    resetButton($button, 'Deactivate');
                }
            },
            error: function() {
                showAdminNotice('Произошла ошибка при деактивации ссылки', 'error');
                resetButton($button, 'Deactivate');
            }
        });
    }

    /**
     * Обработка массовых действий
     */
    function handleBulkAction(e) {
        var action = $('select[name="bulk_action"]').val();
        var action2 = $('select[name="bulk_action2"]').val();
        var selectedAction = action || action2;
        
        if (!selectedAction) {
            e.preventDefault();
            showAdminNotice('Пожалуйста, выберите действие', 'warning');
            return;
        }
        
        var selectedItems = $('input[name="subscription_tokens[]"]:checked');
        
        if (selectedItems.length === 0) {
            e.preventDefault();
            showAdminNotice('Пожалуйста, выберите подписки для обработки', 'warning');
            return;
        }
        
        var actionText = getActionText(selectedAction);
        
        if (!confirm('Вы уверены, что хотите ' + actionText.toLowerCase() + ' ' + selectedItems.length + ' подписок?')) {
            e.preventDefault();
            return;
        }
    }

    /**
     * Обработка поиска
     */
    function handleSearch(e) {
        var searchTerm = $('input[name="s"]').val().trim();
        
        if (searchTerm.length < 2) {
            e.preventDefault();
            showAdminNotice('Введите минимум 2 символа для поиска', 'warning');
            return;
        }
    }

    /**
     * Обработка изменения товара
     */
    function handleProductChange() {
        var selectedOption = $(this).find('option:selected');
        var price = selectedOption.data('price');
        
        if (price) {
            $('#subscription_amount').val(price);
        }
    }

    /**
     * Обработка формы создания
     */
    function handleCreateForm(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('input[type="submit"]');
        var originalText = $submitButton.val();
        
        // Валидация формы
        if (!validateCreateForm($form)) {
            return;
        }
        
        // Показываем индикатор загрузки
        $submitButton.val('Создание...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'create_subscription_link',
                product_id: $('#product_id').val(),
                subscription_amount: $('#subscription_amount').val(),
                customer_name: $('#customer_name').val(),
                customer_email: $('#customer_email').val(),
                nonce: subscriptionLinkAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    handleCreateSuccess(response.data);
                } else {
                    showAdminNotice('Ошибка: ' + response.data, 'error');
                }
            },
            error: function() {
                showAdminNotice('Произошла ошибка при создании ссылки подписки', 'error');
            },
            complete: function() {
                $submitButton.val(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Валидация формы создания
     */
    function validateCreateForm($form) {
        var productId = $('#product_id').val();
        var amount = $('#subscription_amount').val();
        var email = $('#customer_email').val();
        
        if (!productId) {
            showAdminNotice('Пожалуйста, выберите товар', 'error');
            return false;
        }
        
        if (!amount || parseFloat(amount) <= 0) {
            showAdminNotice('Пожалуйста, введите корректную сумму', 'error');
            return false;
        }
        
        if (email && !isValidEmail(email)) {
            showAdminNotice('Пожалуйста, введите корректный email', 'error');
            return false;
        }
        
        return true;
    }

    /**
     * Обработка успешного создания
     */
    function handleCreateSuccess(data) {
        // Показываем результат
        $('#subscription-link-url').val(data.link);
        $('#test-link').attr('href', data.link);
        $('#subscription-result').show();
        $('#create-subscription-form').hide();
        
        showAdminNotice('Ссылка подписки создана успешно!', 'success');
    }

    /**
     * Обработка копирования ссылки
     */
    function handleCopyLink(e) {
        e.preventDefault();
        
        var linkInput = $('#subscription-link-url');
        var link = linkInput.val();
        
        if (!link) {
            showAdminNotice('Нет ссылки для копирования', 'error');
            return;
        }
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(link).then(function() {
                showAdminNotice('Ссылка скопирована в буфер обмена', 'success');
            });
        } else {
            // Fallback для старых браузеров
            linkInput.select();
            document.execCommand('copy');
            showAdminNotice('Ссылка скопирована в буфер обмена', 'success');
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
            showAdminNotice('Пожалуйста, введите корректный URL', 'error');
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
            showAdminNotice('Пожалуйста, исправьте ошибки в полях URL', 'error');
            return false;
        }
    }

    /**
     * Показать уведомление в админке
     */
    function showAdminNotice(message, type) {
        // Удаляем существующие уведомления
        $('.subscription-admin-notice').remove();
        
        // Создаем новое уведомление
        var $notice = $('<div class="notice subscription-admin-notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
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
     * Сброс кнопки
     */
    function resetButton($button, text) {
        $button.prop('disabled', false).text(text);
    }

    /**
     * Получить текст действия
     */
    function getActionText(action) {
        var actions = {
            'deactivate': 'Деактивировать',
            'delete': 'Удалить',
            'activate': 'Активировать'
        };
        
        return actions[action] || action;
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
    window.SubscriptionAdmin = {
        showNotice: showAdminNotice,
        validateEmail: isValidEmail,
        validateUrl: isValidUrl
    };

})(jQuery);

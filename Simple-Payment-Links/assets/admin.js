/**
 * JavaScript для админки Simple Payment Links
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
        // Копирование ссылок при клике
        $('.wp-list-table input[readonly]').on('click', function() {
            this.select();
            document.execCommand('copy');
            showNotice('Ссылка скопирована в буфер обмена!', 'success');
        });
        
        // Удаление ссылок
        $('.button-link-delete').on('click', handleDeleteLink);
    }
    
    /**
     * Обработка удаления ссылки
     */
    function handleDeleteLink(e) {
        e.preventDefault();
        
        var $button = $(this);
        var linkId = $button.data('link-id');
        var $row = $button.closest('tr');
        
        console.log('Delete button clicked, link ID:', linkId);
        
        if (!confirm('Удалить эту ссылку? Это действие нельзя отменить.')) {
            return;
        }
        
        // Показываем индикатор загрузки
        $button.prop('disabled', true).text('Удаление...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_payment_link',
                link_id: linkId
            },
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                    });
                    showNotice('Ссылка удалена', 'success');
                } else {
                    showNotice('Ошибка удаления: ' + response.data, 'error');
                    $button.prop('disabled', false).text('Удалить');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', xhr.responseText);
                showNotice('Ошибка удаления ссылки: ' + error, 'error');
                $button.prop('disabled', false).text('Удалить');
            }
        });
    }

    /**
     * Показать уведомление
     */
    function showNotice(message, type) {
        // Удаляем существующие уведомления
        $('.spl-admin-notice').remove();
        
        // Создаем новое уведомление
        var $notice = $('<div class="notice spl-admin-notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Добавляем уведомление в начало страницы
        $('.wrap h1').after($notice);
        
        // Автоматически скрываем через 3 секунды
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

})(jQuery);

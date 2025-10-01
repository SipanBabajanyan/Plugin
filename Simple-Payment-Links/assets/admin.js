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

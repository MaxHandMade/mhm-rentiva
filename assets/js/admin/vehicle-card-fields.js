/* global jQuery */
(function ($) {
    'use strict';

    function updateHiddenInput($selectedList, $input) {
        const items = [];

        $selectedList.find('li').each(function () {
            const $item = $(this);
            items.push({
                type: $item.data('fieldType'),
                key: $item.data('fieldKey')
            });
        });

        $input.val(JSON.stringify(items));
    }

    function refreshEmptyState($list) {
        if ($list.find('li').length === 0) {
            $list.addClass('is-empty');
        } else {
            $list.removeClass('is-empty');
        }
    }

    $(function () {
        const $wrapper = $('.mhm-card-fields-wrapper');
        if (!$wrapper.length) {
            return;
        }

        const $selectedList = $('#mhm-card-fields-selected');
        const $availableList = $('#mhm-card-fields-available');
        const $input = $('#mhm-vehicle-card-fields-input');

        if (!$selectedList.length || !$availableList.length || !$input.length) {
            return;
        }

        $selectedList.add($availableList).sortable({
            connectWith: '.mhm-card-fields-list',
            placeholder: 'mhm-card-fields-placeholder',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            update: function () {
                updateHiddenInput($selectedList, $input);
                refreshEmptyState($selectedList);
                refreshEmptyState($availableList);
            },
            receive: function () {
                updateHiddenInput($selectedList, $input);
                refreshEmptyState($selectedList);
                refreshEmptyState($availableList);
            }
        }).disableSelection();

        $availableList.on('click', 'li', function () {
            $(this).appendTo($selectedList);
            updateHiddenInput($selectedList, $input);
            refreshEmptyState($selectedList);
            refreshEmptyState($availableList);
        });

        $selectedList.on('click', '.remove-field', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const $item = $(this).closest('li');
            $item.appendTo($availableList);
            updateHiddenInput($selectedList, $input);
            refreshEmptyState($selectedList);
            refreshEmptyState($availableList);
        });

        // Initial state refresh
        refreshEmptyState($selectedList);
        refreshEmptyState($availableList);
        updateHiddenInput($selectedList, $input);
    });
})(jQuery);


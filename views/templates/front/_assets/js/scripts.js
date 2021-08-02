/**
 * Codecranes
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://codecranes.com/modules/prestashop-advanced-search/license
 *
 * @author     Codecranes
 * @copyright  Copyright (c) Codecranes (https://codecranes.com)
 * @license    https://codecranes.com/modules/prestashop-advanced-search/license
 */

if (typeof jQuery === 'undefined')
    throw new Error('jQuery error, library doesn\'t work');

(function ($) {
    $.fn.ccSearch = function () {
        var config = codecranes_advancedsearch_config,
            $form = $(this).closest('form'),
            $form_div = $form.parent(),
            $search_input = $(this),
            search_queries = [];

        $search_input.attr('autocomplete', 'off').removeClass('ui-autocomplete-input').removeClass('ui-autocomplete-loading');

        $form.attr('action', config['ajax_link']);
        $form.find('input[name="controller"]').remove();

        $form.on('submit', function (e) {
            if ($.trim($(this).find('input[type="text"]').val()) == '') {
                e.preventDefault();
                $(this).addClass('error_empty');
            }
        });

        $search_input.on('keyup', function (e) {
            if (e.keyCode == '9')
                return;

            ajaxSearch($(this));
        });

        $search_input.on('focusout', function (e) {
            $(this).closest('form').removeClass('error_empty');
        });

        $search_input.on('focusin', function () {
            if (config['show_again'] != '1' || $.trim($(this).val()) == '')
                return;

            var $this_form_popup = $(this).closest('form').parent().find('.codecranes_advancedsearch_input');

            if ($this_form_popup.length < '1') // wyszukaj
                ajaxSearch($(this));
            else
                $this_form_popup.show();
        });

        if (config['category'] == '1' && config['categories_select']) {
            $form.append(config['categories_select']);

            $form.find('.cc_search_categories').on('change', function (e) {
                ajaxSearch($(this));
            });
        }

        $(document).on('click', function (e) {
            if ($('#search_widget .codecranes_advancedsearch_input').length < '1')
                return;

            var $target = $(e.target);

            if ($target.closest('#search_widget').length < '1')
                $('#search_widget .codecranes_advancedsearch_input').hide();
        });

        function ajaxSearch($this) {
            $form_div = $this.closest('form').parent();
            $form_div.find('.codecranes_advancedsearch_input').remove();

            if ($('#old_wrapper').length > '0') {
                $('.cc_content_search').remove();
                $('#old_wrapper').attr('id', 'wrapper');
                $('#wrapper').show();
            }

            for (var i = 0; i < search_queries.length; i++)
                search_queries[i].abort();

            search_queries = [];

            closeSearchResult();

            var $post_form = $this.closest('form'),
                $post_search_input = $post_form.find('input[type="text"]'),
                $post_search_category = config['category'] == '1' ? $post_form.find('.cc_search_categories') : false,
                search_from_header = $this.closest('header').length > '0' ? true : false,
                searchURL = config['ajax_link'];

            if ($post_search_input.val().length < '1')
                return;

            $post_form.removeClass('error_empty');

            var search_query = $.ajax(
                {
                    method: 'GET',
                    url: searchURL,
                    headers: {
                        "cache-control": "no-cache"
                    },
                    dataType: 'json',
                    data: {
                        'ajax': '1',
                        'c': $post_search_category ? $post_search_category.val() : false,
                        's': $post_search_input.val()
                    }
                })
                .done(function (resp) {
                    $form_div.append(resp.rendered_search_popup);
                });

            search_queries.push(search_query);
        }

        function closeSearchResult() {
            $('#cc_search_results').remove();
        }
    };
})(jQuery);

jQuery(document).ready(function ($) {
    if ($('#search_widget form input[type="text"]').length > 0)
        $('#search_widget form input[type="text"]').ccSearch();
});

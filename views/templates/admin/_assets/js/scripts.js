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
    $.fn.ccAjaxInfo = function () {
        var $this_div = $(this),
            in_action = false,
            IntervalQty = 0,
            IntervalGetInfo = setInterval(getInfo, 10000);

        setTimeout(function () {
            getInfo();
        }, 1000);

        function getInfo() {

            if (in_action)
                return;

            if (IntervalQty >= 20) {
                clearInterval(IntervalGetInfo);
                return;
            }

            IntervalQty++;
            in_action = true;

            $.ajax(
                {
                    method: 'POST',
                    url: 'https://modules.codecranes.com/prestashop/advanced-search/getinfo/',
                    dataType: 'json',
                    data: codecranes_advancedsearch_ajax_info
                })
                .done(function (resp) {
                    if (typeof resp.type == 'undefined')
                        return;

                    var html = resp.desc;

                    if (typeof resp.color != 'undefined') {
                        var span;

                        switch (resp.color) {
                            case 'red':
                                span = '<span style="color: #FF0000;"></span>';
                                break;
                            case 'orange':
                                span = '<span style="color: #FFA500;"></span>';
                                break;
                            case 'green':
                                span = '<span style="color: #00CC00;"></span>';
                                break;
                        }
                        $this_div.html(span).find('span').text(html);
                    }
                    else
                        $this_div.text(html);
                    clearInterval(IntervalGetInfo);
                })
                .error(function (request, status, error) {
                    in_action = false;
                });
        }
    };

    $.fn.ccCollapse = function () {
        var $this_header = $(this).find('.panel-heading');

        $this_header.on('click', function () {
            var $this_body = $(this).parent().find('.form-wrapper');

            if ($this_body.is(':hidden'))
                $this_body.slideDown();
            else
                $this_body.slideUp();
        });
    };
})(jQuery);

jQuery(document).ready(function ($) {
    if ($('#codecranes_info .codecranes_ajax_info').length > 0)
        $('#codecranes_info .codecranes_ajax_info').ccAjaxInfo();

    if ($('#module_form .cc_collapse').length > 0)
        $('#module_form .cc_collapse').ccCollapse();
});

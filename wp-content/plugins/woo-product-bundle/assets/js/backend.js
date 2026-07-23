'use strict';

(function ($) {
    var woosb_timeout = null;

    $(function () {
        // options page
        woosb_active_options();

        // product page
        woosb_active_settings();

        // total price
        if ($('#product-type').val() == 'woosb') {
            woosb_change_price();
        }

        woosb_init_item();

        // arrange
        woosb_arrange();
    });

    $(document).on('change', '.optional_checkbox', function () {
        woosb_init_item();
    });

    $(document).on('click touch', '#woosb_search_settings_btn', function (e) {
        // open search settings popup
        e.preventDefault();

        var title = $('#woosb_search_settings').attr('data-title');

        $('#woosb_search_settings').dialog({
            minWidth: 540, title: title, modal: true, dialogClass: 'wpc-dialog', open: function () {
                $('.ui-widget-overlay').bind('click', function () {
                    $('#woosb_search_settings').dialog('close');
                });
            },
        });
    });

    $(document).on('click touch', '.woosb_selected .woosb-li-product .woosb_item_qty_input', function (e) {
        if (!$(this).closest('.woosb-li-product').hasClass('woosb-li-open')) {
            $(this).closest('.woosb-li-product').addClass('woosb-li-open');
            $(this).closest('.woosb-li-product').find('.woosb-li-body').slideDown();
        }
    });

    $(document).on('click touch', '.woosb_selected .woosb-li-product .woosb-li-head', function (e) {
        if (!$(e.target).closest('.woosb_item_qty_input').length && !$(e.target).closest('.type').length && !$(e.target).closest('.woosb-remove').length && !$(e.target).closest('.move').length) {
            $(this).closest('.woosb-li-product').toggleClass('woosb-li-open');
            $(this).closest('.woosb-li-product').find('.woosb-li-body').slideToggle();
        }
    });

    // import/export
    $(document).on('click touch', '.woosb-import-export', function (e) {
        e.preventDefault();

        var ids = $('#woosb_selected').find('input, select').serialize() || 0;

        if (!$('#woosb_import_export').length) {
            $('body').append('<div id=\'woosb_import_export\'></div>');
        }

        $('#woosb_import_export').html('Loading...');

        $('#woosb_import_export').dialog({
            minWidth: 460, title: 'Import/Export', modal: true, dialogClass: 'wpc-dialog', open: function () {
                $('.ui-widget-overlay').bind('click', function () {
                    $('#woosb_import_export').dialog('close');
                });
            },
        });

        var data = {
            action: 'woosb_import_export', ids: ids, nonce: woosb_vars.nonce,
        };

        $.post(ajaxurl, data, function (response) {
            $('#woosb_import_export').html(response);
        });
    });

    $(document).on('click touch', '.woosb-import-export-save', function (e) {
        if (confirm('Are you sure?')) {
            $(this).addClass('disabled');

            var ids = $('.woosb_import_export_data').val();
            var data = {
                action: 'woosb_import_export_save', ids: ids, nonce: woosb_vars.nonce,
            };

            $.post(ajaxurl, data, function (response) {
                $('#woosb_import_export').dialog('close');
                $('#woosb_selected .woosb-ul').html(response);
                woosb_arrange();
                woosb_change_price();
                woosb_init_item();
            });
        }
    });

    $(document).on('click touch', '#woosb_search_settings_update', function (e) {
        // save search settings
        e.preventDefault();

        $('#woosb_search_settings').addClass('woosb_search_settings_updating');

        var data = {
            action: 'woosb_update_search_settings',
            nonce: woosb_vars.nonce,
            limit: $('.woosb_search_limit').val(),
            sku: $('.woosb_search_sku').val(),
            id: $('.woosb_search_id').val(),
            exact: $('.woosb_search_exact').val(),
            sentence: $('.woosb_search_sentence').val(),
            same: $('.woosb_search_same').val(),
            show_image: $('.woosb_search_show_image').val(),
            types: $('.woosb_search_types').val(),
        };

        $.post(ajaxurl, data, function (response) {
            $('#woosb_search_settings').removeClass('woosb_search_settings_updating');
        });
    });

    $(document).on('change', '.woosb_change_price, .woosb_price_format, .woosb_variations_selector', function () {
        woosb_active_options();
    });

    $(document).on('change', '#product-type', function () {
        woosb_active_settings();
    });

    $(document).on('change', '#woosb_bulk_actions', function () {
        var val = $(this).val();
        var qty = 0;
        var reg = new RegExp('^[0-9]+$');

        if (val !== 'none') {
            switch (val) {
                case 'enable_optional':
                    if (confirm('Are you sure?')) {
                        $('.woosb_selected').find('.optional_checkbox').prop('checked', true).trigger('change');
                    }

                    break;
                case 'disable_optional':
                    if (confirm('Are you sure?')) {
                        $('.woosb_selected').find('.optional_checkbox').prop('checked', false).trigger('change');
                    }

                    break;
                case 'set_qty_default':
                    qty = window.prompt('Please enter a number', '1');

                    if ((null != qty) && reg.test(qty)) {
                        $('.woosb_selected').find('.woosb_item_qty_input').val(qty).trigger('change');
                    }

                    break;
                case 'set_qty_min':
                    qty = window.prompt('Please enter a number', '1');

                    if ((null != qty) && reg.test(qty)) {
                        $('.woosb_selected').find('.optional_min_input').val(qty).trigger('change');
                    }

                    break;
                case 'set_qty_max':
                    qty = window.prompt('Please enter a number', '10');

                    if ((null != qty) && reg.test(qty)) {
                        $('.woosb_selected').find('.optional_max_input').val(qty).trigger('change');
                    }

                    break;
            }
        }

        $(this).val('none');
    });

    $(document).on('change', '#woosb_discount, #woosb_discount_amount', function () {
        woosb_change_price();
    });

    // set regular price
    $(document).on('click touch', '#woosb_set_regular_price', function () {
        if ($('#woosb_disable_auto_price').is(':checked')) {
            $('li.general_tab a').trigger('click');
            $('#_regular_price').focus();
        } else {
            alert('You must disable auto calculate price first!');
        }
    });

    // set total limits
    $(document).on('click touch', '#woosb_total_limits', function () {
        if ($(this).is(':checked')) {
            $('.woosb_show_if_total_limits').show();
        } else {
            $('.woosb_show_if_total_limits').hide();
        }
    });

    // checkbox
    $(document).on('change', '#woosb_disable_auto_price', function () {
        if ($(this).is(':checked')) {
            $('#_regular_price').prop('readonly', false);
            $('#_sale_price').prop('readonly', false);
            $('.woosb_tr_show_if_auto_price').hide();
        } else {
            $('#_regular_price').prop('readonly', true);
            $('#_sale_price').prop('readonly', true);
            $('.woosb_tr_show_if_auto_price').show();
        }

        if ($('#product-type').val() == 'woosb') {
            woosb_change_price();
        }
    });

    // add text
    $(document).on('click touch', '.woosb_add_text', function (e) {
        e.preventDefault();

        var $this = $(this);

        $('.woosb_selected').addClass('woosb_selected_loading');
        $this.addClass('disabled');

        var data = {
            action: 'woosb_add_text', nonce: woosb_vars.nonce,
        };

        $.post(ajaxurl, data, function (response) {
            $('#woosb_selected .woosb-ul').append(response);
            $this.removeClass('disabled');
            $('.woosb_selected').removeClass('woosb_selected_loading');
        });
    });

    // search input
    $(document).on('keyup', '#woosb_keyword', function () {
        if ($('#woosb_keyword').val() != '') {
            $('#woosb_loading').show();

            if (woosb_timeout != null) {
                clearTimeout(woosb_timeout);
            }

            woosb_timeout = setTimeout(woosb_ajax_get_data, 300);
            return false;
        }
    });

    // actions on search result items
    $(document).on('click touch', '#woosb_results .woosb-li', function () {
        $(this).find('.woosb-remove').attr('aria-label', 'Remove').html('×');
        $('#woosb_selected .woosb-ul').append($(this));
        $('#woosb_results').html('').hide();
        $('#woosb_keyword').val('');
        woosb_change_price();
        woosb_init_item();
        woosb_arrange();
        return false;
    });

    // change qty of each item
    $(document).on('keyup change', '#woosb_selected .qty input', function () {
        woosb_change_price();
        return false;
    });

    // actions on selected items
    $(document).on('click touch', '#woosb_selected .woosb-remove', function () {
        $(this).closest('.woosb-li').remove();
        woosb_change_price();
        return false;
    });

    // hide search result box if click outside
    $(document).on('click touch', function (e) {
        if ($(e.target).closest('#woosb_results').length == 0) {
            $('#woosb_results').html('').hide();
        }
    });

    function woosb_arrange() {
        $('#woosb_selected .woosb-ul').sortable({
            handle: '.move',
        });
    }

    function woosb_active_options() {
        if ($('.woosb_price_format').val() === 'custom') {
            $('.woosb_tr_show_if_price_format_custom').show();
        } else {
            $('.woosb_tr_show_if_price_format_custom').hide();
        }

        if ($('.woosb_change_price').val() === 'yes_custom') {
            $('.woosb_change_price_custom').show();
        } else {
            $('.woosb_change_price_custom').hide();
        }

        if ($('.woosb_variations_selector').val() === 'woovr') {
            $('.woosb_show_if_woovr').show();
        } else {
            $('.woosb_show_if_woovr').hide();
        }
    }

    function woosb_active_settings() {
        if ($('#product-type').val() == 'woosb') {
            $('li.general_tab').addClass('show_if_woosb');
            $('#general_product_data .pricing').addClass('show_if_woosb');
            $('._tax_status_field').closest('.options_group').addClass('show_if_woosb');
            $('#_downloadable').closest('label').addClass('show_if_woosb').removeClass('show_if_simple');
            $('#_virtual').closest('label').addClass('show_if_woosb').removeClass('show_if_simple');

            $('.show_if_external').hide();
            $('.show_if_simple').show();
            $('.show_if_woosb').show();

            $('.product_data_tabs li').removeClass('active');
            $('.product_data_tabs li.woosb_tab').addClass('active');

            $('.panel-wrap .panel').hide();
            $('#woosb_settings').show();

            if ($('#woosb_total_limits').is(':checked')) {
                $('.woosb_show_if_total_limits').show();
            } else {
                $('.woosb_show_if_total_limits').hide();
            }

            if ($('#woosb_disable_auto_price').is(':checked')) {
                $('.woosb_tr_show_if_auto_price').hide();
            } else {
                $('.woosb_tr_show_if_auto_price').show();
            }

            woosb_change_price();
        } else {
            $('li.general_tab').removeClass('show_if_woosb');
            $('#general_product_data .pricing').removeClass('show_if_woosb');
            $('._tax_status_field').closest('.options_group').removeClass('show_if_woosb');
            $('#_downloadable').closest('label').removeClass('show_if_woosb').addClass('show_if_simple');
            $('#_virtual').closest('label').removeClass('show_if_woosb').addClass('show_if_simple');

            $('#_regular_price').prop('readonly', false);
            $('#_sale_price').prop('readonly', false);

            if ($('#product-type').val() != 'grouped') {
                $('.general_tab').show();
            }

            if ($('#product-type').val() == 'simple') {
                $('#_downloadable').closest('label').show();
                $('#_virtual').closest('label').show();
            }
        }
    }

    function woosb_round(value, decimals) {
        return Number(Math.round(value + 'e' + decimals) + 'e-' + decimals);
    }

    function woosb_format_money(number, places, symbol, thousand, decimal) {
        number = number || 0;
        places = !isNaN((places = Math.abs(places))) ? places : 2;
        symbol = symbol !== undefined ? symbol : '$';
        thousand = thousand || ',';
        decimal = decimal || '.';

        var negative = number < 0 ? '-' : '',
            i = parseInt((number = woosb_round(Math.abs(+number || 0), places).toFixed(places)), 10) + '', j = 0;

        if (i.length > 3) {
            j = i.length % 3;
        }

        return (symbol + negative + (j ? i.substr(0, j) + thousand : '') + i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + thousand) + (places ? decimal + woosb_round(Math.abs(number - i), places).toFixed(places).slice(2) : ''));
    }

    function woosb_init_item() {
        $('.terms_config:not(.terms_config_select_woo)').each(function () {
            $(this).addClass('terms_config_select_woo').find('select').selectWoo();
        });

        $('.optional_checkbox').each(function () {
            if ($(this).is(':checked')) {
                $(this).closest('.qty_config').find('.optional_min_max').show();
                $(this).closest('.woosb-li-product').addClass('woosb-li-product-optional');
            } else {
                $(this).closest('.qty_config').find('.optional_min_max').hide();
                $(this).closest('.woosb-li-product').removeClass('woosb-li-product-optional');
            }
        });
    }

    function woosb_change_price() {
        if ($('#product-type').val() == 'woosb') {
            var total = 0;
            var total_max = 0;
            var total_sale = $('#woosb_discount_amount').val() ? -parseFloat($('#woosb_discount_amount').val()) : 0;
            var discount_percentage = !$('#woosb_discount_amount').val() && $('#woosb_discount').val() ? parseFloat($('#woosb_discount').val()) : 0;

            $('#woosb_selected .woosb-li-product').each(function () {
                var li_qty = parseFloat($(this).find('.qty input').val() ? $(this).find('.qty input').val() : 1);
                var li_price = parseFloat($(this).data('price'));
                var li_price_max = parseFloat($(this).data('price-max'));

                total += li_price * li_qty;
                total_max += li_price_max * li_qty;

                if (woosb_vars.round_price) {
                    total_sale += parseFloat((li_price * (100 - discount_percentage) / 100).toFixed(parseInt(woosb_vars.price_decimals))) * li_qty;
                } else {
                    total_sale += li_price * li_qty;
                }
            });

            if (!woosb_vars.round_price) {
                total_sale = parseFloat(total_sale * (100 - discount_percentage) / 100).toFixed(parseInt(woosb_vars.price_decimals));
            }

            if (total == total_max) {
                $('#woosb_regular_price').html(woosb_format_money(total, woosb_vars.price_decimals, '', woosb_vars.price_thousand_separator, woosb_vars.price_decimal_separator));
            } else {
                $('#woosb_regular_price').html(woosb_format_money(total, woosb_vars.price_decimals, '', woosb_vars.price_thousand_separator, woosb_vars.price_decimal_separator) + ' - ' + woosb_format_money(total_max, woosb_vars.price_decimals, '', woosb_vars.price_thousand_separator, woosb_vars.price_decimal_separator));
            }

            if (!$('#woosb_disable_auto_price').is(':checked')) {
                $('#_regular_price').prop('readonly', true).val(woosb_format_money(total, woosb_vars.price_decimals, '', woosb_vars.price_thousand_separator, woosb_vars.price_decimal_separator)).trigger('change');

                if (total_sale < total) {
                    $('#_sale_price').prop('readonly', true).val(woosb_format_money(total_sale, woosb_vars.price_decimals, '', woosb_vars.price_thousand_separator, woosb_vars.price_decimal_separator)).trigger('change');
                } else {
                    $('#_sale_price').prop('readonly', true).val('').trigger('change');
                }
            }
        }
    }

    function woosb_ajax_get_data() {
        // ajax search product
        woosb_timeout = null;

        var ids = [];

        $('#woosb_selected').find('.woosb-li-product').each(function () {
            ids.push($(this).attr('data-id'));
        });

        var data = {
            action: 'woosb_get_search_results',
            nonce: woosb_vars.nonce,
            keyword: $('#woosb_keyword').val(),
            ids: ids.join(),
        };

        $.post(ajaxurl, data, function (response) {
            $('#woosb_results').show();
            $('#woosb_results').html(response);
            $('#woosb_loading').hide();
        });
    }
})(jQuery);

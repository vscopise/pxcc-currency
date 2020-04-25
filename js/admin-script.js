jQuery(document).ready(function($){
        /* global pxcc_admin_object */
        var name_label = pxcc_admin_object.name_label;
        var code_label = pxcc_admin_object.code_label;
        var sign_label = pxcc_admin_object.sign_label;
        var rate_label = pxcc_admin_object.rate_label;
        var no_currency_msg = pxcc_admin_object.no_currency_msg;
        var currency_in_use_msg = pxcc_admin_object.currency_in_use_msg;
        var ajax_url = pxcc_admin_object.ajax_url;
        var loading = '<span class="spinner is-active" style="float: left;"></span>';
        $('.remove_item').on('click', function handler() {
                var tbody = $('.currencies_settings').find('tbody');
                if ( tbody.find('tr.current').size() > 0 ) {
                        $('.remove_item').off('click');
                        $('.remove_item').after(loading);
                        current = tbody.find('tr.current');
                        id = current.find('input[name="pxcc_currencies[id][]"]').val();
                        $.ajax({
                            type: 'post',
                            url: ajax_url,
                            data:{
                                action: 'pxcc_remove_currency',
                                id: id
                            },
                            success: function(response){
                                if (response == 0) {
                                    current.remove();
                                } else {
                                    alert( currency_in_use_msg );
                                }
                                $('.remove_item').nextAll().remove();
                            },
                            complete: function() {
                                   $('.remove_item').click(handler);
                            }
                        });

                } else {
                        alert( no_currency_msg );
                        //$( "#dialog" ).dialog();
                }
                return false;
        });
        $('.currencies_settings .insert').click(function() {
                var tbody = $('.currencies_settings').find('tbody');
                var id_currency = tbody.find('tr').size();

                var last_row = tbody.find('tr.new').size();
                var row = '<tr class="new">\\n\
                                <td width="40%">\
                                        <input type="hidden" value="'+id_currency+'" name="pxcc_currencies[id][]" />\
                                        <input type="text" name="pxcc_currencies[name][]" placeholder="' + name_label + '" />\
                                </td>\
                                <td width="10%">\
                                        <input type="text"  name="pxcc_currencies[code][]" placeholder="' + code_label + '" />\
                                </td>\\n\
                                <td width="10%">\
                                        <input type="text"  name="pxcc_currencies[sign][]" placeholder="' + sign_label + '" />\
                                </td>\
                                <td width="40%">\
                                        <input type="text" name="pxcc_currencies[rate][]" placeholder="' + rate_label + '" />\
                                </td>\
                        </tr>';
                var num_tr_new = tbody.find('tr.new').size();
                var last_tr_input = tbody.find('tr.new').last().find('input[type="text"]');

                if ( num_tr_new === 0 || tbody.find('tr.new').last()) {
                        tbody.append( row );
                } else {
                        alert('error')
                }
               /*if ( tbody.find('tr.current').size() > 0 ) {
                        tbody.find('tr.current').after( row );
                } else {
                        tbody.append( row );
                }*/
        });
});
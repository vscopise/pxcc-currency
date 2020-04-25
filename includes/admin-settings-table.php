<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$wc_currencies = get_woocommerce_currencies();
$wc_currency = get_option( 'woocommerce_currency' );
$wc_currency_name = $wc_currencies[$wc_currency];
$wc_currency_code = $wc_currency;
$wc_currency_symbol = get_woocommerce_currency_symbol() ;

$pxcc_currencies = get_option( 'pxcc_currencies_data', array() );
?>
<table class="currencies_settings wc_input_table sortable widefat">
        <thead>
                <th width="40%"><?php _e('Name', 'pxe-custom-currencies') ?></th>
                <th width="10%"><?php _e('Code', 'pxe-custom-currencies') ?></th>
                <th width="10%"><?php _e('Sign', 'pxe-custom-currencies') ?></th>
                <th width="40%"><?php _e('Rate', 'pxe-custom-currencies') ?></th>
        </thead>
        <tbody>
                <tr>
                        <td>
                                <input type="text" disabled="disabled" value="<?php echo $wc_currency_name?>" />
                        </td>
                        <td>
                                <input type="text" disabled="disabled" value="<?php echo $wc_currency_code?>" />
                        </td>
                        <td>
                                <input type="text" disabled="disabled" value="<?php echo $wc_currency_symbol ?>" />
                        </td>
                        <td>
                                <input type="text" disabled="disabled" value="1" />
                        </td>
                </tr>
            <?php if ( count($pxcc_currencies) > 0 ) : foreach( $pxcc_currencies as $data ) : ?>
                <tr>
                        <td>
                                <input type="hidden" value="<?php echo esc_attr( $data['id'] ) ?>" name="pxcc_currencies[id][]" />
                                <input type="text" value="<?php echo esc_attr( $data['name'] ) ?>" name="pxcc_currencies[name][]" />
                        </td>
                        <td>
                                <input type="text" value="<?php echo esc_attr( $data['code'] ) ?>" name="pxcc_currencies[code][]" />
                        </td>
                        <td>
                                <input type="text" value="<?php echo esc_attr( $data['sign'] ) ?>" name="pxcc_currencies[sign][]" />
                        </td>
                        <td>
                                <input type="text" value="<?php echo esc_attr( $data['rate'] ) ?>" name="pxcc_currencies[rate][]" />
                        </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4">
                    <span class="button insert"><?php _e('Insert Currency', 'pxe-custom-currencies') ?></span>
                    <span class="button remove_item"><?php _e('Remove selected Currency', 'pxe-custom-currencies') ?></span>
                </th>
            </tr>
        </tfoot>
</table>
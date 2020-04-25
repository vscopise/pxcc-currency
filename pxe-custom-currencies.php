<?php
/**
 * Plugin Name:       PXE Woocommerce Custom Currencies
 * Plugin URI:        
 * Description:       Add extra currencies to a Woocommerce store.
 * Version:           1.0.0
 * Author:            Pixie
 * Author URI:        http://www.pixie.com.uy/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pxe-custom-currencies
 * Domain Path:       /languages/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action('plugins_loaded', array ( PXE_Custom_Currencies::get_instance(), 'plugin_setup' ) );

class PXE_Custom_Currencies {
    
        protected static $instance = NULL;
        
        public static function get_instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self;
                }
                return self::$instance; 
        }
        
        public function plugin_setup() {
                add_action( 'pxe_cc_cron_hook', __CLASS__ . '::pxcc_get_remote_currencies' );
        }
        
        public function __construct() {
                add_filter( 'woocommerce_general_settings', __CLASS__ . '::settings', 999 );
                add_action( 'woocommerce_admin_field_pxcc_dynamic_currencies_table', __CLASS__ . '::admin_settings_table' );
                add_action( 'woocommerce_update_option_pxcc_dynamic_currencies_table', __CLASS__ . '::pxcc_update_currencies_data' );
                add_action( 'admin_notices', __CLASS__ . '::admin_notices' );
                
                add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_scripts' );
                add_action( 'init', __CLASS__ . '::load_textdomain' );
                add_action( 'woocommerce_product_options_general_product_data', __CLASS__ . '::pxcc_edit_product_currencies' );
                add_action( 'woocommerce_process_product_meta', __CLASS__ . '::pxcc_edit_product_currencies_save' );
                
                add_action( 'woocommerce_shipping_free_shipping_is_available', __CLASS__ . '::free_shipping_is_available', 10, 3 );
                
                
                add_filter( 'woocommerce_cart_needs_shipping', __CLASS__ . '::cart_needs_shipping', 10, 1 );
                
                add_action( 'wp_ajax_pxcc_remove_currency', __CLASS__ . '::pxcc_remove_currency' );
                add_action( 'wp_ajax_nopriv_pxcc_remove_currency', __CLASS__ . '::pxcc_remove_currency' );
                
                add_filter( 'woocommerce_cart_item_price', __CLASS__ . '::pxcc_cart_item_price', 10, 2 );
                add_filter( 'woocommerce_cart_item_subtotal', __CLASS__ . '::pxcc_cart_item_subtotal', 10, 2 );
                //add_filter( 'woocommerce_product_get_price', __CLASS__ . '::pxcc_product_get_price', 10, 2 );
                
                add_filter( 'woocommerce_get_price_html', __CLASS__ . '::get_price_html', 10, 2 );
                
                add_filter( 'woocommerce_format_sale_price', __CLASS__ . '::pxcc_format_sale_price', 10, 3 );
                
                add_action( 'woocommerce_checkout_create_order_line_item', __CLASS__ . '::checkout_create_order_line_item', 10, 4 );
                add_action( 'woocommerce_checkout_create_order', __CLASS__ . '::checkout_create_order', 10, 2 );
                
                add_action( 'wp_enqueue_scripts', __CLASS__ . '::pxcc_enqueue_scripts' );

		        add_filter( 'woocommerce_cart_totals_order_total_html', __CLASS__ . '::cart_totals_order_total_html' );
		        add_filter( 'woocommerce_cart_subtotal', __CLASS__ . '::cart_subtotal', 10, 3 );
        }
        
        function cart_needs_shipping() {
            return true;
        }
        
        function free_shipping_is_available( $is_available, $package, $shipping ) {
            global $woocommerce;
            
            $cart_content = $woocommerce->cart->cart_contents;
            $cart_price = 0;
            foreach ( $cart_content as $cart_item ) {
                $pxcc_currency = get_post_meta( $cart_item['product_id'], '_pxcc_currency', true );
                if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
                    $pxcc_currencies_data = get_option('pxcc_currencies_data');
                    $element = array_search( $pxcc_currency, array_column( $pxcc_currencies_data, 'id' ) );
                    $currency_rate = $pxcc_currencies_data[$element]['rate'];
                    if ( $cart_item['data']->is_on_sale() ) {
                        $item_price = $cart_item['data'] -> get_sale_price();
                    } else {
                        $item_price = $cart_item['data'] -> get_regular_price();
                    }
                    $item_price = $item_price * $currency_rate;
                } else {
                    $item_price = (float) $cart_item['data']->price;
                }
                $item_quantity = (int) $cart_item['quantity'];
                
                $cart_price += $item_quantity * $item_price;
                
            }
            
            return $cart_price > 3000 ? true: false;
        }
        
        function get_price_html( $price, $product ) {
            
            $product_id = $product->id;
            $pxcc_currency = get_post_meta( $product_id, '_pxcc_currency', true );
            if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
                $pxcc_currencies_data = get_option('pxcc_currencies_data');
                $element = array_search( $pxcc_currency, array_column( $pxcc_currencies_data, 'id' ) );
                $currency_symbol = $pxcc_currencies_data[$element]['sign'];
                $price = $product->price;
                $price = '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">'. $currency_symbol . '</span>' . $price . '</span>';
            }
            return $price;
        }
        
        function checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
            $product_id = $values['product_id'];
            $pxcc_currency = get_post_meta( $product_id, '_pxcc_currency', true );
            if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
                $pxcc_currencies_data = get_option('pxcc_currencies_data');
                $element = array_search( $pxcc_currency, array_column( $pxcc_currencies_data, 'id' ) );
                $currency_rate = $pxcc_currencies_data[$element]['rate'];
                $price = $values['line_total'] * $currency_rate;
                $item->set_total( $price );
            }
        }
        
        function checkout_create_order( $order, $data ) {
            $new_total = 0;
            foreach( $order->get_items() as $item_id => $item ) {
                $new_total += $item -> get_total();
            }
            
            $shipping_cost = $order->get_shipping_total();
            $new_total += $shipping_cost;
            
            $order->set_total( $new_total );
        }
        
        
        
        function cart_subtotal( $cart_subtotal, $compound, $cart_content ) {
            global $woocommerce;

            $subtotal = 0;

		    $pxcc_currencies_data = get_option('pxcc_currencies_data');
            $element = array_search( $pxcc_currency, array_column( $pxcc_currencies_data, 'id' ) );
            $currency_rate = $pxcc_currencies_data[$element]['rate'];
            
            $cart_content = $woocommerce->cart->cart_contents;
            
            foreach ( $cart_content as $cart_item ) {
			    $pxcc_currency = get_post_meta( $cart_item['product_id'], '_pxcc_currency', true );
			    if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
				    if ( $cart_item['data']->is_on_sale() ) {
                        $item_price = $cart_item['data'] -> get_sale_price();
                    } else {
                        $item_price = $cart_item['data'] -> get_regular_price();
                    }
				    $item_price = $item_price * $currency_rate;
                } else {
                    $item_price = (float) $cart_item['data']->price;
                }
                $cart_item_quantity = (int) $cart_item['quantity'];
                
                $subtotal += $cart_item_quantity * $item_price;
                
            }
            
            $correct_subtotal = number_format( $subtotal, 2 );

            $currency_symbol = get_woocommerce_currency_symbol();
            
            $cart_subtotal = '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">'. $currency_symbol . '</span>' . $correct_subtotal . '</span>';
            return $cart_subtotal;
        }
        
	    function cart_totals_order_total_html( $total ) {
		    global $woocommerce;

            $total = 0;

		    $pxcc_currencies_data = get_option('pxcc_currencies_data');
            $element = array_search( $pxcc_currency, array_column( $pxcc_currencies_data, 'id' ) );
            $currency_rate = $pxcc_currencies_data[$element]['rate'];

            $cart_content = $woocommerce->cart->cart_contents;
            foreach ( $cart_content as $cart_item ) {
			    $pxcc_currency = get_post_meta( $cart_item['product_id'], '_pxcc_currency', true );
			    if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
				    if ( $cart_item['data']->is_on_sale() ) {
                        $item_price = $cart_item['data'] -> get_sale_price();
                    } else {
                        $item_price = $cart_item['data'] -> get_regular_price();
                    }

				    $item_price = $item_price * $currency_rate;

                } else {
                    $item_price = (float) $cart_item['data']->price;
                }
                $cart_item_quantity = (int) $cart_item['quantity'];
                $total += $cart_item_quantity * $item_price;
            }
            
            $shipping = $woocommerce->cart->get_shipping_total();
            $total += $shipping;
                
            $correct_total = number_format( $total, 2 );

            $currency_symbol = get_woocommerce_currency_symbol();
            $total = '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">'. $currency_symbol . '</span>' . $correct_total . '</span>';
            return $total;
	    }

        function pxcc_enqueue_scripts() {
                wp_enqueue_style( 'dashicons' );
        }
        
        function pxcc_format_sale_price( $price, $regular_price, $sale_price ) {
                global $product;
                $pxcc_currency = get_post_meta( $product->id, '_pxcc_currency', true );
                if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
                        $currency_symbol = pxcc_currency_symbol( $product->id );
                        $wc_price = '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">' . $currency_symbol.'</span>' . $regular_price . '</span>';
                        $price = '<del>' . ( is_numeric( $regular_price ) ? $wc_price : $regular_price ) . '</del> <ins>' . ( is_numeric( $sale_price ) ? wc_price( $sale_price ) : $sale_price ) . '</ins>';
                }
            
                return $price;
        }
        
        function pxcc_product_get_price( $price, $product ) {
                $pxcc_currency = get_post_meta( $product->id, '_pxcc_currency', true );
                if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
                        $pxcc_currencies_data = get_option('pxcc_currencies_data');
                        $element = array_search( $pxcc_currency, array_column( $pxcc_currencies_data, 'id' ) );
                        $currency_rate = $pxcc_currencies_data[$element]['rate'];
                        $price = $price * $currency_rate;
                }
                return $price;
        }
        
        function pxcc_cart_item_subtotal( $wc, $item ) {
                global $woocommerce;
		        $currency_symbol = get_woocommerce_currency_symbol();

                $pxcc_currency = get_post_meta( $item['product_id'], '_pxcc_currency', true );
                if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
			        $pxcc_currencies_data = get_option('pxcc_currencies_data');
                    $element = array_search( $pxcc_currency, array_column( $pxcc_currencies_data, 'id' ) );
			        $currency_rate = $pxcc_currencies_data[$element]['rate'];

			        if ( $item['data']->is_on_sale() ) {
                        $item_price = $item['data'] -> get_sale_price();
                    } else {
                        $item_price = $item['data'] -> get_regular_price();
                    }

                    $wc_price_decimals = wc_get_price_decimals();
                    $item_quantity = (int) $item['quantity'];

			        $cart_item_subtotal_price = round( $item_quantity * $item_price * $currency_rate, $wc_price_decimals );

                    $wc_price = '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">' . $currency_symbol.'</span>' . $cart_item_subtotal_price . '</span>';

                    $note_message = __('The value was obtained based on the current rate', 'pxe-custom-currencies');
                    $note = '<span class="dashicons dashicons-warning" style="color: #c7c7c7; margin-left: 3px; font-size: 18px; line-height: 22px;" title="' . $note_message . '"></span>';

                        $wc = $wc_price . $note;
                }
                return $wc;
        }
        
        function pxcc_cart_item_price( $wc, $item ) {

                $pxcc_currency = get_post_meta( $item['product_id'], '_pxcc_currency', true );
                if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
                        $pxcc_currencies_data = get_option('pxcc_currencies_data');
                        $element = array_search( $pxcc_currency, array_column( $pxcc_currencies_data, 'id' ) );
                        if ( $item['data']->is_on_sale() ) {
                            $item_price = $item['data'] -> get_sale_price();
                        } else {
                            $item_price = $item['data'] -> get_regular_price();
                        }                      
                        
                        $currency_symbol = pxcc_currency_symbol( $item['product_id'] );
                        
                        $wc = '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">' . $currency_symbol . '</span>' . $item_price . '</span>';
                }
                return $wc;
        }
        
        function pxcc_remove_currency() {
                global $wpdb;
                $id = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
                $currencies = $wpdb->get_results("SELECT * FROM $wpdb->postmeta
                    WHERE meta_key = '_pxcc_currency' AND  meta_value = '$id'");
                echo count( $currencies ) == 0 ? 0 : 1;
                wp_die();
        }

        function pxcc_price( $return, $price, $args ) {
                global $post;
                if ( $product = wc_get_product($post->ID) ) {
                    $pxcc_currency = get_post_meta( $product->id, '_pxcc_currency', true );
                    if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
                            $pxcc_currencies_data = get_option('pxcc_currencies_data');
                            $element = array_search( $pxcc_currency, array_column( $pxcc_currencies_data, 'id' ) );
                            $currency_rate = $pxcc_currencies_data[$element]['rate'];
                            $product_price = $product->price * $currency_rate;
                            $currency_symbol = pxcc_currency_symbol( $product->id );
                            $decimal_separator = wc_get_price_decimal_separator();
                            $thousand_separator = wc_get_price_thousand_separator();
                            $decimals = wc_get_price_decimals();                        
                            $product_price = wc_trim_zeros( number_format( $product_price, $decimals, $decimal_separator, $thousand_separator ) );
                            $return = '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">' . $currency_symbol.'</span>' . $product_price . '</span>';
                    }
                    
                }
                return $return;
        }
        
        function pxcc_edit_product_currencies_save( $post_id ) {
                $pxcc_currency =  $_POST['_pxcc_currency'];
                update_post_meta( $post_id, '_pxcc_currency', esc_attr( $pxcc_currency ) );
        }
        
        function pxcc_edit_product_currencies() {
                global $woocommerce, $post;
                
                $pxcc_currency = get_post_meta( $post->ID, '_pxcc_currency', true );
                $pxcc_currencies_data = (array) get_option('pxcc_currencies_data');
                $woocommerce_currencies = get_woocommerce_currencies();
                $woocommerce_currency = get_option( 'woocommerce_currency' );
                $woocommerce_currency_name = $woocommerce_currencies[$woocommerce_currency];

                ?>
                <div class="options_group">
                        <p class="form-field product_field_type">
                                <label for="product_field_type"><?php _e( 'Currency', 'pxe-custom-currencies' ); ?></label>
                                <select name="_pxcc_currency">
                                        <option value="0" <?php selected( $pxcc_currency, '0' ) ?>><?php echo $woocommerce_currency_name ?></option>
                                        <?php foreach( $pxcc_currencies_data as $currency ) : ?>
                                        <option value="<?php echo $currency['id'] ?>" <?php selected( $pxcc_currency, $currency['id'] ) ?>><?php echo $currency['name']?></option>
                                        <?php endforeach; ?>
                                </select>
                        </p>
                </div>
                <?php
        }
        
        public static function load_textdomain() {
                load_plugin_textdomain( 'pxe-custom-currencies', false, basename( dirname( __FILE__ ) ) . '/languages' );
        }
        
        public static function admin_scripts( $hook ) {
                if ( 'woocommerce_page_wc-settings' != $hook ) return;
                
                $translation_array = array(
                    'name_label' => __( 'Name', 'pxe-custom-currencies' ),
                    'code_label' => __( 'Code', 'pxe-custom-currencies' ),
                    'sign_label' => __( 'Sign', 'pxe-custom-currencies' ),
                    'rate_label' => __( 'Rate', 'pxe-custom-currencies' ),
                    'no_currency_msg' => __( 'No currency(es) selected', 'pxe-custom-currencies' ),
                    'currency_in_use_msg' => __( 'This Currency is used', 'pxe-custom-currencies' ),
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                );
                wp_register_script( 'pxcc_admin_script', plugins_url('/js/admin-script.js', __FILE__) );
                wp_localize_script( 'pxcc_admin_script', 'pxcc_admin_object', $translation_array );
                wp_enqueue_script( 'pxcc_admin_script' );
        }
        
        public static function settings( $settings ) {
                $settings[] = array( 'type' => 'title', 'name' => __( 'Additional Currencies', 'pxe-custom-currencies' ), 'id' => 'pxcc_settings_title' );
                $settings[] = array( 
                        'name' => 'Open Exchange App ID', 
                        'id' => 'pcc_open_exchange_app_idd',
                        'type' => 'text', 
                        'css' => 'min-width:350px',
                        'desc' => sprintf( __( 'Get your API key <a href="%s" target="_blank">Here</a>', 'pxe-custom-currencies' ), 'https://openexchangerates.org/'),
                );
                $settings[] = array( 'type' => 'pxcc_dynamic_currencies_table', 'id' => 'pxcc_dynamic_currencies_table' );
                $settings[] = array( 'type' => 'sectionend', 'id' => 'pxcc_settings_end' );

                return $settings;
        }
        
        public static function admin_settings_table() {
                require_once dirname( __FILE__ ) . '/includes/admin-settings-table.php';
        }
                
        public function pxcc_update_currencies_data() {
                $pxcc_currencies_new = (array) $_POST['pxcc_currencies'];
                $pxcc_currencies_data = array();
                foreach( $pxcc_currencies_new as $fields => $settings ){
                        foreach( $settings as $key => $setting ){
                                if($setting != '') {
                                        //$pxcc_currencies_data[$key][$fields] = 1 / $setting;
                                        $pxcc_currencies_data[$key][$fields] = $setting;
                                }
                        }
                }
                update_option('pxcc_currencies_data', $pxcc_currencies_data);
        }
        
        public static function admin_notices() {
                if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option('active_plugins') ) ) ) {
                    ?>
                            <div class="error">
                                    <p><?php _e( 'PXE Custom Currencies requires WooCommerce.', 'pxe-custom-currencies' ); ?></p>
                            </div>
                    <?php
                }
        }
                
        
        public static function activated() {
                if ( !wp_next_scheduled( 'pxe_cc_cron_hook' ) ) {
                        wp_schedule_event( strtotime( '9:00:00' ), 'daily', 'pxe_cc_cron_hook' );
                }
	}
        
        public static function deactivated() {
                wp_clear_scheduled_hook( 'pxe_cc_cron_hook' );
        }
        
        public function pxcc_get_remote_currencies() {
                $pxcc_currencies_data = (array) get_option( 'pxcc_currencies_data' );
                $woo_currency = get_woocommerce_currency();
                if ( count($pxcc_currencies_data) > 0 ) {
                        $pcc_open_exchange_app_idd = get_option( 'pcc_open_exchange_app_idd', 1 );
                        if ( '' !== $pcc_open_exchange_app_idd ) {
                                $oxc_url  = "https://openexchangerates.org/api/latest.json?app_id=" . $pcc_open_exchange_app_idd;
                                $request = wp_remote_get( $oxc_url );

                                if ( ! is_wp_error( $request ) ) {
                                        $body = wp_remote_retrieve_body( $request );
                                        $rates_data = json_decode( $body )->rates;

                                        $local_rate = $rates_data -> $woo_currency;
                                        foreach ( $pxcc_currencies_data as $key=>$currency ) {
                                                //$currency_rate = $rates_data -> $currency['code'];
                                                //$pxcc_currencies_data[$key][rate] = $local_rate * $currency_rate;
                                                $currency_rate = $currency['code'] == 'USD' ? 1 : $rates_data -> $currency['code'];
                                                $pxcc_currencies_data[$key][rate] = $currency_rate * $local_rate;
                                        }
                                        update_option( 'pxcc_currencies_data', $pxcc_currencies_data );
                                }
                        }
                }
        }
}

register_activation_hook( __FILE__, array( 'PXE_Custom_Currencies', 'activated' ) );
register_deactivation_hook( __FILE__, array( 'PXE_Custom_Currencies', 'deactivated' ) );

function pxcc_currency_symbol( $product_id ) {
        $pxcc_currency = get_post_meta( $product_id, '_pxcc_currency', true );
        if ( $pxcc_currency != '' && $pxcc_currency != '0' ) {
                $pxcc_currencies_data = get_option('pxcc_currencies_data');
                $element = array_search( $pxcc_currency, array_column( $pxcc_currencies_data, 'id' ) );
                $currency_symbol = $pxcc_currencies_data[$element]['sign'];
        } else {
                $currency_symbol = get_woocommerce_currency_symbol( $currency );
        }

        return $currency_symbol;
}
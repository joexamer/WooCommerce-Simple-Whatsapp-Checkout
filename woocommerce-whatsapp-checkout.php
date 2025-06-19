<?php
/**
 * Plugin Name: WooCommerce Simple Whatsapp Checkout
 * Description: Adds a simple WhatsApp checkout option for single WooCommerce stores.
 * Version: 1.0
 * Author: DigaTopia, Yousef Amer
 * Author URI: https://github.com/joexamer
 * Plugin URI: https://github.com/joexamer/WooCommerce-Simple-Whatsapp-Checkout
 * Requires at least WooCommerce : 4.1
 * Tested up to Wordpress : 6.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function WC_Simple_WA_check_woocommece_active(){
	if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		echo "<div class='error'><p><strong>WooCommerce Simple Whatsapp Checkout</strong> requires <strong>WooCommerce plugin.</strong>&nbsp; Please <a href='https://wordpress.org/plugins/woocommerce' target=_blank>install</a> and activate it.</p></div>";
	}
}
add_action('admin_notices', 'WC_Simple_WA_check_woocommece_active');

add_filter( 'woocommerce_general_settings', 'wc_simple_wa_add_settings_field' );
function wc_simple_wa_add_settings_field( $settings ) {
    $updated_settings = array();
    foreach ( $settings as $section ) {
        if ( isset( $section['id'] ) && 'checkout_options' == $section['id'] && isset( $section['type'] ) && 'title' == $section['type'] ) {
            $updated_settings[] = array(
                'name'     => __( 'WhatsApp Checkout Settings', 'woocommerce-simple-whatsapp-checkout' ),
                'type'     => 'title',
                'desc'     => __( 'Settings for the Simple WhatsApp Checkout plugin.', 'woocommerce-simple-whatsapp-checkout' ),
                'id'       => 'wc_simple_wa_options'
            );
            $updated_settings[] = array(
                'name'     => __( 'WhatsApp Number', 'woocommerce-simple-whatsapp-checkout' ),
                'desc_tip' => __( 'Enter the WhatsApp number to receive order notifications (include country code, e.g., +1234567890).', 'woocommerce-simple-whatsapp-checkout' ),
                'id'       => 'wc_simple_wa_whatsapp_number',
                'type'     => 'text',
                'desc'     => __( 'The WhatsApp number where order details will be sent.', 'woocommerce-simple-whatsapp-checkout' ),
                'css'      => 'min-width:300px;',
                'autoload' => false,
            );
             $updated_settings[] = array(
                'type'     => 'sectionend',
                'id'       => 'wc_simple_wa_options'
            );
        }
        $updated_settings[] = $section;
    }
    return $updated_settings;
}

add_action( 'woocommerce_thankyou', 'wc_simple_wa_thankyou_redirect', 10, 1 );
function wc_simple_wa_thankyou_redirect( $order_id ) {
    if ( ! $order_id ) {
        return;
    }

    $order = wc_get_order( $order_id );
    $whatsapp_number = get_option( 'wc_simple_wa_whatsapp_number' );

    if ( empty( $whatsapp_number ) ) {
        return;
    }

    $msg = "*New Order Details (Order #{$order_id}):*\n\n";

    $msg .= "*Billing Details:*\n";
    $msg .= "Name: " . $order->get_billing_first_name() . " " . $order->get_billing_last_name() . "\n";
    $msg .= "Address: " . $order->get_billing_address_1() . "\n";
    if ( $order->get_billing_address_2() ) {
        $msg .= $order->get_billing_address_2() . "\n";
    }
    $msg .= "City: " . $order->get_billing_city() . "\n";
    $msg .= "State: " . $order->get_billing_state() . "\n";
    $msg .= "Postcode: " . $order->get_billing_postcode() . "\n";
    $msg .= "Country: " . $order->get_billing_country() . "\n";
    $msg .= "Email: " . $order->get_billing_email() . "\n";
    $msg .= "Phone: " . $order->get_billing_phone() . "\n\n";

    // Shipping Details (if different from billing)
    if ( wc_shipping_enabled() && $order->get_shipping_address_1() ) {
        if ( $order->get_shipping_address_1() !== $order->get_billing_address_1() || $order->get_shipping_first_name() !== $order->get_billing_first_name() ) {
             $msg .= "*Shipping Details:*\n";
             $msg .= "Name: " . $order->get_shipping_first_name() . " " . $order->get_shipping_last_name() . "\n";
             $msg .= "Address: " . $order->get_shipping_address_1() . "\n";
             if ( $order->get_shipping_address_2() ) {
                 $msg .= $order->get_shipping_address_2() . "\n";
             }
             $msg .= "City: " . $order->get_shipping_city() . "\n";
             $msg .= "State: " . $order->get_shipping_state() . "\n";
             $msg .= "Postcode: " . $order->get_shipping_postcode() . "\n";
             $msg .= "Country: " . $order->get_shipping_country() . "\n\n";
        }
    }


    $msg .= "*Order Items:*\n";
    foreach ( $order->get_items() as $item_id => $item ) {
        $product = $item->get_product();
        $msg .= $item->get_quantity() . " x " . $item->get_name();
        if ( $product && $product->get_sku() ) {
            $msg .= " (SKU: " . $product->get_sku() . ")";
        }
        $msg .= " - " . wc_price( $order->get_line_total( $item, true, true ), array( 'currency' => $order->get_currency() ) ) . "\n";
    }
    $msg .= "\n";

    $msg .= "*Order Totals:*\n";
    $msg .= "Subtotal: " . wc_price( $order->get_subtotal(), array( 'currency' => $order->get_currency() ) ) . "\n";
    if ( $order->get_total_discount() > 0 ) {
         $msg .= "Discount: " . wc_price( $order->get_total_discount(), array( 'currency' => $order->get_currency() ) ) . "\n";
    }
    foreach ( $order->get_shipping_methods() as $shipping_method ) {
        $msg .= "Shipping: " . $shipping_method->get_method_title() . " - " . wc_price( $shipping_method->get_total(), array( 'currency' => $order->get_currency() ) ) . "\n";
    }
    foreach ( $order->get_tax_totals() as $tax_total ) {
        $msg .= $tax_total->label . ": " . $tax_total->formatted_amount . "\n";
    }
    $msg .= "Payment Method: " . $order->get_payment_method_title() . "\n";
    $msg .= "*Order Total: " . wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) . "*\n\n";

    if ( $order->get_customer_note() ) {
        $msg .= "*Customer Note:*\n" . $order->get_customer_note() . "\n\n";
    }

    $msg .= "Thank you!";

    $whatsapp_url = 'https://api.whatsapp.com/send?phone=' . urlencode( $whatsapp_number ) . '&text=' . rawurlencode( $msg );

    echo "
    <script type='text/javascript'>
        setTimeout(function() {
            window.open('" . esc_url_raw( $whatsapp_url ) . "', '_blank');
        }, 3000); // 3000 milliseconds = 3 seconds
    </script>";
}

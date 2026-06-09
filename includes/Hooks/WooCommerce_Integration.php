<?php
namespace WP_GHL\Hooks;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce Integration
 * Syncs new customers and order events to GHL
 */
class WooCommerce_Integration {

    public function __construct() {
        if ( ! get_option( 'wp_ghl_enable_woo' ) ) return;
        if ( ! class_exists( 'WooCommerce' ) ) return;

        add_action( 'woocommerce_payment_complete',       [ $this, 'on_payment_complete' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'on_order_completed' ] );
    }

    public function on_payment_complete( int $order_id ): void {
        $this->sync_order( $order_id, [ 'WooCommerce Customer', 'Payment Received' ] );
    }

    public function on_order_completed( int $order_id ): void {
        $this->sync_order( $order_id, [ 'WooCommerce Customer', 'Order Completed' ] );
    }

    private function sync_order( int $order_id, array $tags ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $contact_data = [
            'first_name'  => $order->get_billing_first_name(),
            'last_name'   => $order->get_billing_last_name(),
            'email'       => $order->get_billing_email(),
            'phone'       => $order->get_billing_phone(),
            'address'     => $order->get_billing_address_1(),
            'city'        => $order->get_billing_city(),
            'state'       => $order->get_billing_state(),
            'postal_code' => $order->get_billing_postcode(),
            'source'      => 'WooCommerce',
            'tags'        => $tags,
            'notes'       => sprintf(
                'Order #%d | Total: %s | Status: %s',
                $order_id,
                $order->get_formatted_order_total(),
                $order->get_status()
            ),
        ];

        $client = new \WP_GHL\API\Client();
        $result = $client->upsert_contact( $contact_data );

        if ( is_wp_error( $result ) ) {
            error_log( '[WP-GHL] WooCommerce sync failed for order ' . $order_id . ': ' . $result->get_error_message() );
        }
    }
}

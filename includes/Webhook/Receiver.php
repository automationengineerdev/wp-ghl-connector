<?php
namespace WP_GHL\Webhook;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Inbound Webhook Receiver
 * Registers a REST API endpoint to receive events from GoHighLevel
 * Endpoint: /wp-json/wp-ghl/v1/webhook
 */
class Receiver {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( 'wp-ghl/v1', '/webhook', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle' ],
            'permission_callback' => [ $this, 'verify_signature' ],
        ] );
    }

    /**
     * Verify the webhook signature using HMAC-SHA256
     */
    public function verify_signature( \WP_REST_Request $request ): bool {
        $secret    = get_option( 'wp_ghl_webhook_secret', '' );
        $signature = $request->get_header( 'x-ghl-signature' );

        if ( empty( $secret ) || empty( $signature ) ) {
            return false;
        }

        $body     = $request->get_body();
        $expected = hash_hmac( 'sha256', $body, $secret );

        return hash_equals( $expected, $signature );
    }

    /**
     * Handle inbound GHL webhook event
     */
    public function handle( \WP_REST_Request $request ): \WP_REST_Response {
        $payload = $request->get_json_params();

        if ( empty( $payload ) ) {
            return new \WP_REST_Response( [ 'error' => 'Empty payload' ], 400 );
        }

        $event_type = $payload['type'] ?? 'unknown';

        /**
         * Fire a WordPress action for each GHL event type
         * Developers can hook into: wp_ghl_event_{type}
         * Example: add_action( 'wp_ghl_event_ContactCreate', function( $payload ) { ... } );
         */
        do_action( 'wp_ghl_event', $event_type, $payload );
        do_action( "wp_ghl_event_{$event_type}", $payload );

        // Built-in handlers
        switch ( $event_type ) {
            case 'ContactCreate':
            case 'ContactUpdate':
                $this->handle_contact_event( $payload );
                break;
            case 'OpportunityCreate':
            case 'OpportunityUpdate':
                $this->handle_opportunity_event( $payload );
                break;
        }

        return new \WP_REST_Response( [ 'received' => true ], 200 );
    }

    private function handle_contact_event( array $payload ): void {
        $contact = $payload['contact'] ?? [];
        if ( empty( $contact ) ) return;

        /**
         * Hook: wp_ghl_contact_received
         * Passes the full contact object from GHL
         */
        do_action( 'wp_ghl_contact_received', $contact );
    }

    private function handle_opportunity_event( array $payload ): void {
        $opportunity = $payload['opportunity'] ?? [];
        if ( empty( $opportunity ) ) return;

        do_action( 'wp_ghl_opportunity_received', $opportunity );
    }
}

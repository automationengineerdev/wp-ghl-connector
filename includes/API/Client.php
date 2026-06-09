<?php
namespace WP_GHL\API;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * GoHighLevel REST API Client
 * Handles all outbound requests to the GHL v1 API
 */
class Client {

    private string $api_key;
    private string $location_id;
    private string $base_url = 'https://rest.gohighlevel.com/v1/';

    public function __construct() {
        $this->api_key     = get_option( 'wp_ghl_api_key', '' );
        $this->location_id = get_option( 'wp_ghl_location_id', '' );
    }

    /**
     * Create or update a GHL contact
     *
     * @param array $contact_data
     * @return array|WP_Error
     */
    public function upsert_contact( array $contact_data ): array|\WP_Error {
        $payload = $this->build_contact_payload( $contact_data );
        return $this->request( 'POST', 'contacts/', $payload );
    }

    /**
     * Add tags to a contact
     *
     * @param string $contact_id
     * @param array  $tags
     * @return array|WP_Error
     */
    public function add_tags( string $contact_id, array $tags ): array|\WP_Error {
        return $this->request( 'POST', "contacts/{$contact_id}/tags/", [ 'tags' => $tags ] );
    }

    /**
     * Create a pipeline opportunity for a contact
     *
     * @param string $contact_id
     * @param string $pipeline_id
     * @param string $stage_id
     * @param string $title
     * @return array|WP_Error
     */
    public function create_opportunity(
        string $contact_id,
        string $pipeline_id,
        string $stage_id,
        string $title
    ): array|\WP_Error {
        return $this->request( 'POST', 'opportunities/', [
            'pipelineId' => $pipeline_id,
            'locationId' => $this->location_id,
            'name'       => $title,
            'stageId'    => $stage_id,
            'contactId'  => $contact_id,
            'status'     => 'open',
        ] );
    }

    /**
     * Update custom fields on a contact
     *
     * @param string $contact_id
     * @param array  $custom_fields  [ ['id' => 'field_id', 'value' => 'val'], ... ]
     * @return array|WP_Error
     */
    public function update_custom_fields( string $contact_id, array $custom_fields ): array|\WP_Error {
        return $this->request( 'PUT', "contacts/{$contact_id}/", [
            'customField' => $custom_fields,
        ] );
    }

    /**
     * Build a normalised contact payload from raw form data
     */
    private function build_contact_payload( array $data ): array {
        $payload = [
            'locationId' => $this->location_id,
            'source'     => 'WordPress',
        ];

        $map = [
            'first_name' => 'firstName',
            'last_name'  => 'lastName',
            'email'      => 'email',
            'phone'      => 'phone',
            'address'    => 'address1',
            'city'       => 'city',
            'state'      => 'state',
            'postal_code' => 'postalCode',
            'name'       => 'name',
            'company'    => 'companyName',
            'website'    => 'website',
            'notes'      => 'notes',
            'source'     => 'source',
            'tags'       => 'tags',
        ];

        foreach ( $map as $input_key => $ghl_key ) {
            if ( ! empty( $data[ $input_key ] ) ) {
                $payload[ $ghl_key ] = sanitize_text_field( $data[ $input_key ] );
            }
        }

        // Merge default tags
        $default_tags = get_option( 'wp_ghl_default_tags', '' );
        if ( $default_tags ) {
            $tags              = array_map( 'trim', explode( ',', $default_tags ) );
            $payload['tags']   = array_unique( array_merge( $payload['tags'] ?? [], $tags ) );
        }

        if ( ! empty( $data['custom_fields'] ) && is_array( $data['custom_fields'] ) ) {
            $payload['customField'] = $data['custom_fields'];
        }

        return $payload;
    }

    /**
     * Execute an HTTP request against the GHL API
     *
     * @param string $method  GET | POST | PUT | DELETE
     * @param string $endpoint
     * @param array  $body
     * @return array|WP_Error
     */
    private function request( string $method, string $endpoint, array $body = [] ): array|\WP_Error {
        if ( empty( $this->api_key ) ) {
            return new \WP_Error( 'no_api_key', __( 'GHL API key is not configured.', 'wp-ghl-connector' ) );
        }

        $args = [
            'method'  => strtoupper( $method ),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Version'       => '2021-07-28',
            ],
            'timeout' => 15,
        ];

        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $url      = $this->base_url . ltrim( $endpoint, '/' );
        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->maybe_log( 'error', $endpoint, $body, $response->get_error_message() );
            return $response;
        }

        $code    = wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        $this->maybe_log( (string) $code, $endpoint, $body, $decoded );

        if ( $code >= 400 ) {
            return new \WP_Error(
                'ghl_api_error',
                $decoded['message'] ?? __( 'Unknown GHL API error', 'wp-ghl-connector' ),
                [ 'status' => $code ]
            );
        }

        return $decoded ?? [];
    }

    private function maybe_log( string $status, string $endpoint, array $payload, mixed $response ): void {
        if ( ! get_option( 'wp_ghl_log_requests' ) ) return;
        error_log( sprintf(
            '[WP-GHL] %s %s | payload: %s | response: %s',
            $status,
            $endpoint,
            wp_json_encode( $payload ),
            wp_json_encode( $response )
        ) );
    }
}

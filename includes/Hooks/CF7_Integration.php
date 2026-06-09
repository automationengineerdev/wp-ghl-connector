<?php
namespace WP_GHL\Hooks;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Contact Form 7 Integration
 * Sends form submissions to GHL when the form tag [ghl_sync] is present
 */
class CF7_Integration {

    public function __construct() {
        if ( ! get_option( 'wp_ghl_enable_cf7' ) ) return;
        add_action( 'wpcf7_mail_sent', [ $this, 'handle_submission' ] );
    }

    public function handle_submission( \WPCF7_ContactForm $form ): void {
        $submission = \WPCF7_Submission::get_instance();
        if ( ! $submission ) return;

        $posted = $submission->get_posted_data();

        // Only sync if [ghl_sync] tag is present in the form
        $tags = $form->scan_form_tags();
        $has_sync_tag = false;
        foreach ( $tags as $tag ) {
            if ( 'ghl_sync' === $tag->basetype ) {
                $has_sync_tag = true;
                break;
            }
        }
        if ( ! $has_sync_tag ) return;

        $contact_data = [
            'first_name' => $posted['your-name']     ?? $posted['first-name'] ?? '',
            'last_name'  => $posted['last-name']      ?? '',
            'email'      => $posted['your-email']     ?? $posted['email']      ?? '',
            'phone'      => $posted['your-phone']     ?? $posted['phone']      ?? '',
            'notes'      => $posted['your-message']   ?? $posted['message']    ?? '',
            'source'     => 'CF7 - ' . $form->title(),
            'tags'       => [ 'WordPress Lead', 'CF7' ],
        ];

        $client   = new \WP_GHL\API\Client();
        $result   = $client->upsert_contact( $contact_data );

        if ( is_wp_error( $result ) ) {
            error_log( '[WP-GHL] CF7 sync failed: ' . $result->get_error_message() );
            return;
        }

        // Assign to default pipeline if configured
        $pipeline_id = get_option( 'wp_ghl_pipeline_id', '' );
        $stage_id    = get_option( 'wp_ghl_stage_id', '' );
        $contact_id  = $result['contact']['id'] ?? '';

        if ( $contact_id && $pipeline_id && $stage_id ) {
            $client->create_opportunity(
                $contact_id,
                $pipeline_id,
                $stage_id,
                'WordPress Lead - ' . $form->title()
            );
        }
    }
}

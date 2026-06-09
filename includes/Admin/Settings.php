<?php
namespace WP_GHL\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function add_menu(): void {
        add_options_page(
            __( 'GHL Connector', 'wp-ghl-connector' ),
            __( 'GHL Connector', 'wp-ghl-connector' ),
            'manage_options',
            'wp-ghl-connector',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings(): void {
        $fields = [
            'api_key'        => __( 'GHL API Key', 'wp-ghl-connector' ),
            'location_id'    => __( 'Location ID', 'wp-ghl-connector' ),
            'pipeline_id'    => __( 'Default Pipeline ID', 'wp-ghl-connector' ),
            'stage_id'       => __( 'Default Stage ID', 'wp-ghl-connector' ),
            'default_tags'   => __( 'Default Tags (comma-separated)', 'wp-ghl-connector' ),
            'webhook_secret' => __( 'Webhook Secret', 'wp-ghl-connector' ),
        ];

        add_settings_section(
            'wp_ghl_main',
            __( 'API Configuration', 'wp-ghl-connector' ),
            null,
            'wp-ghl-connector'
        );

        foreach ( $fields as $key => $label ) {
            register_setting( 'wp_ghl_settings', 'wp_ghl_' . $key, [
                'sanitize_callback' => 'sanitize_text_field',
            ] );
            add_settings_field(
                'wp_ghl_' . $key,
                $label,
                [ $this, 'render_text_field' ],
                'wp-ghl-connector',
                'wp_ghl_main',
                [ 'key' => $key, 'type' => ( $key === 'api_key' ? 'password' : 'text' ) ]
            );
        }

        // Checkbox fields
        foreach ( [ 'enable_cf7', 'enable_woo', 'log_requests' ] as $key ) {
            register_setting( 'wp_ghl_settings', 'wp_ghl_' . $key );
        }
    }

    public function render_text_field( array $args ): void {
        $key   = $args['key'];
        $type  = $args['type'] ?? 'text';
        $value = get_option( 'wp_ghl_' . $key, '' );
        printf(
            '<input type="%s" name="wp_ghl_%s" id="wp_ghl_%s" value="%s" class="regular-text" autocomplete="off" />',
            esc_attr( $type ),
            esc_attr( $key ),
            esc_attr( $key ),
            esc_attr( $value )
        );
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $webhook_url = rest_url( 'wp-ghl/v1/webhook' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WP GHL Connector Settings', 'wp-ghl-connector' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wp_ghl_settings' );
                do_settings_sections( 'wp-ghl-connector' );
                submit_button();
                ?>
            </form>
            <hr>
            <h2><?php esc_html_e( 'Inbound Webhook URL', 'wp-ghl-connector' ); ?></h2>
            <p><?php esc_html_e( 'Use this URL to receive events from GoHighLevel:', 'wp-ghl-connector' ); ?></p>
            <code><?php echo esc_url( $webhook_url ); ?></code>
        </div>
        <?php
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'settings_page_wp-ghl-connector' !== $hook ) return;
        // Enqueue admin assets if needed
    }
}

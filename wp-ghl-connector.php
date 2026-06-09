<?php
/**
 * Plugin Name: WP GHL Connector
 * Plugin URI:  https://technicalva.com
 * Description: Connects WordPress forms and WooCommerce events to GoHighLevel CRM via REST API and webhooks. Supports contact creation, tagging, pipeline assignment, and custom field mapping.
 * Version:     1.0.0
 * Author:      Kenneth Buan Pineda
 * Author URI:  https://technicalva.com
 * License:     GPL-2.0+
 * Text Domain: wp-ghl-connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WP_GHL_VERSION', '1.0.0' );
define( 'WP_GHL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_GHL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload classes
spl_autoload_register( function ( $class ) {
    $prefix   = 'WP_GHL\\';
    $base_dir = WP_GHL_PLUGIN_DIR . 'includes/';
    $len      = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $relative_class = substr( $class, $len );
    $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

/**
 * Main plugin class — singleton pattern
 */
final class WP_GHL_Connector {

    private static ?WP_GHL_Connector $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    public function init(): void {
        load_plugin_textdomain( 'wp-ghl-connector', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        // Boot modules
        new WP_GHL\Admin\Settings();
        new WP_GHL\API\Client();
        new WP_GHL\Hooks\CF7_Integration();
        new WP_GHL\Hooks\WooCommerce_Integration();
        new WP_GHL\Webhook\Receiver();
    }

    public function activate(): void {
        $defaults = [
            'api_key'       => '',
            'location_id'   => '',
            'pipeline_id'   => '',
            'stage_id'      => '',
            'default_tags'  => '',
            'webhook_secret' => wp_generate_password( 32, false ),
            'enable_cf7'    => '1',
            'enable_woo'    => '0',
            'log_requests'  => '0',
        ];
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( 'wp_ghl_' . $key ) ) {
                add_option( 'wp_ghl_' . $key, $value );
            }
        }
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        flush_rewrite_rules();
    }
}

WP_GHL_Connector::instance();

# WP GHL Connector

A WordPress plugin that connects your WordPress site to GoHighLevel CRM via REST API and webhooks.

## Features

- **Contact sync** from Contact Form 7 submissions and WooCommerce orders
- **Pipeline automation** — automatically creates GHL opportunities on form submission
- **Tag management** — apply default and event-specific tags to contacts
- **Custom field mapping** — map form fields to GHL custom fields
- **Inbound webhook receiver** — receive GHL events in WordPress with HMAC-SHA256 signature verification
- **Extensible hook system** — `do_action( 'wp_ghl_event_{type}', $payload )` for custom event handling

## Requirements

- WordPress 6.0+
- PHP 8.0+
- A GoHighLevel account with API access

## Installation

1. Upload the `wp-ghl-connector` folder to `/wp-content/plugins/`
2. Activate the plugin via the Plugins menu
3. Go to **Settings > GHL Connector** and enter your API credentials

## Configuration

| Setting | Description |
|---|---|
| GHL API Key | Your GoHighLevel API key |
| Location ID | Your GHL sub-account location ID |
| Default Pipeline ID | Pipeline to assign new leads to |
| Default Stage ID | Pipeline stage for new leads |
| Default Tags | Comma-separated tags applied to every contact |
| Webhook Secret | HMAC secret for verifying inbound GHL webhooks |

## Contact Form 7 Usage

Add the `[ghl_sync]` tag to any CF7 form to enable GHL sync for that form.

## WooCommerce Usage

Enable WooCommerce integration in settings. Contacts are synced on payment complete and order completed events.

## Inbound Webhook

Your webhook endpoint is:
```
https://yoursite.com/wp-json/wp-ghl/v1/webhook
```

Configure this URL in your GHL account under Settings > Integrations > Webhooks.

## Developer Hooks

```php
// Fire when any GHL event is received
add_action( 'wp_ghl_event', function( $type, $payload ) { }, 10, 2 );

// Fire for a specific event type
add_action( 'wp_ghl_event_ContactCreate', function( $payload ) { } );

// Fire when a contact is received
add_action( 'wp_ghl_contact_received', function( $contact ) { } );
```

## Author

Kenneth Buan Pineda  
Kenneth Buan Pineda Computer Programming Services  
DTI Reg. No. 8183127  
https://technicalva.com

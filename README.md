# WordPress Encryption Utilities - Secure Storage for Sensitive Data

A lightweight utility library for WordPress that provides authenticated encryption and decryption of sensitive data stored in options, transients, and meta tables. Perfect for protecting API keys, passwords, and tokens in your WordPress applications.

## Features

* 🔐 **Simple API**: Clean object-oriented interface with trait-based architecture
* 🛡️ **AES-256-GCM**: Authenticated encryption — tampering with stored ciphertext is detected, not silently decrypted
* 🔑 **WordPress Integration**: Seamlessly works with WordPress options, transients, and meta
* 🧩 **Automatic Salt Detection**: Uses WordPress salts for enhanced security
* 🔍 **Prefix Detection**: Automatically detects encrypted values
* 🔄 **Custom Keys**: Support for custom encryption keys
* 📋 **Constants Support**: Automatically checks for WordPress constants before database storage
* 🎯 **Auto-Interception**: Transparent decryption of get_option() calls

## Requirements

* PHP 7.4 or later
* WordPress 5.0 or later
* OpenSSL PHP extension

## Installation

Install via Composer:

```bash
composer require arraypress/wp-encryption
```

## Basic Usage

### Creating an Encryption Manager

```php
use ArrayPress\EncryptionUtils\Manager;

// Create an instance with a prefix for your plugin/theme
$encryption = new Manager( 'my_plugin' );

// With custom encryption key
$encryption = new Manager( 'my_plugin', 'custom-encryption-key' );

// Disable auto-interception if needed
$encryption = new Manager( 'my_plugin', null, false );
```

### Working with WordPress Options

```php
// Store encrypted options
$encryption->update_option( 'api_key', 'your-secret-api-key' );
$encryption->update_option( 'access_token', 'bearer-token-xyz' );

// Retrieve decrypted values
$api_key = $encryption->get_option( 'api_key' );
$token = $encryption->get_option( 'access_token', 'default-value' );

// Delete encrypted options
$encryption->delete_option( 'api_key' );
```

### Working with Transients

```php
// Store encrypted transients with expiration
$encryption->set_transient( 'auth_token', 'bearer-token-xyz', HOUR_IN_SECONDS );

// Retrieve decrypted transients
$token = $encryption->get_transient( 'auth_token' );
if ( false === $token ) {
    // Token expired or doesn't exist
}
```

### Working with User Meta

```php
// Store encrypted user meta
$encryption->update_user_meta( $user_id, 'access_key', 'user-specific-key' );

// Retrieve decrypted user meta
$user_key = $encryption->get_user_meta( $user_id, 'access_key', 'default' );
```

### Working with Post Meta

```php
// Store encrypted post meta
$encryption->update_post_meta( $post_id, 'payment_details', json_encode($details) );

// Retrieve decrypted post meta
$payment_json = $encryption->get_post_meta( $post_id, 'payment_details' );
$payment_details = json_decode( $payment_json, true );
```

## WordPress Constants Support

The encryption manager automatically checks for WordPress constants before falling back to database storage:

```php
// Define constants in wp-config.php
define( 'MY_PLUGIN_API_KEY', 'production-api-key' );
define( 'MY_PLUGIN_SECRET_TOKEN', 'production-secret' );

// These will automatically use the constants
$api_key = $encryption->get_option( 'api_key' ); // Returns MY_PLUGIN_API_KEY
$secret = $encryption->get_option( 'secret_token' ); // Returns MY_PLUGIN_SECRET_TOKEN

// Database updates are ignored when constants are defined
$encryption->update_option( 'api_key', 'new-key' ); // No effect, constant takes precedence
```

## Auto-Interception Feature

Enable auto-interception to transparently decrypt values when using standard WordPress functions:

```php
$encryption = new Manager( 'my_plugin' );

// Track options for auto-interception
$encryption->track_option( 'api_key' );
$encryption->track_option( 'secret_token' );

// Now standard WordPress functions return decrypted values
$api_key = get_option( 'my_plugin_api_key' ); // Automatically decrypted!
```

## Direct Encryption/Decryption

```php
// Encrypt values directly
$encrypted = $encryption->encrypt( 'sensitive-data' );
echo $encrypted; // Outputs: __MY_PLUGIN_ENCRYPTED__BASE64STRING

// Decrypt values
$original = $encryption->decrypt( $encrypted );
echo $original; // Outputs: sensitive-data

// Check if value is encrypted
if ( $encryption->is_encrypted( $value ) ) {
    // Value is encrypted
}
```

## Advanced Usage

### Temporary Disable Auto-Interception

```php
// Useful during settings save to prevent conflicts
$was_enabled = $encryption->is_auto_intercept_enabled();
if ( $was_enabled ) {
    $encryption->disable_auto_interception();
}

// Perform operations that need raw database access
$encryption->update_option( 'api_key', $new_value );

// Re-enable if it was enabled
if ( $was_enabled ) {
    $encryption->enable_auto_interception();
}
```

### Get Option Information

```php
// Get detailed information about an option
$info = $encryption->get_option_info( 'api_key' );
/*
Returns array:
[
    'value' => 'decrypted-value',
    'source' => 'constant|database|default',
    'constant' => 'MY_PLUGIN_API_KEY', // if from constant
    'option' => 'my_plugin_api_key',   // if from database
    'is_encrypted' => true             // if database value is encrypted
]
*/
```

### Custom Encryption Keys

```php
// Use a custom encryption key
$encryption = new Manager( 'my_plugin', 'my-custom-key' );

// Change the key later
$encryption->change_key( 'new-encryption-key' );

// Use WordPress salts (default behavior)
$encryption->change_key(); // null = use WordPress salts
```

## Integration Example: WooCommerce Plugin

```php
<?php
use ArrayPress\WP\Encryption\Manager;

class MyWooCommercePlugin {
    private Manager $encryption;
    
    public function __construct() {
        $this->encryption = new Manager( 'my_wc_plugin' );
        
        // Track sensitive options for auto-interception
        $this->encryption->track_option( 'api_key' );
        $this->encryption->track_option( 'webhook_secret' );
        
        add_action( 'woocommerce_update_options_integration_my_plugin', [ $this, 'save_settings' ] );
    }
    
    public function save_settings() {
        // Temporarily disable auto-interception during save
        $this->encryption->disable_auto_interception();
        
        // Save encrypted settings
        $this->encryption->update_option( 'api_key', $_POST['api_key'] ?? '' );
        $this->encryption->update_option( 'webhook_secret', $_POST['webhook_secret'] ?? '' );
        
        // Re-enable auto-interception
        $this->encryption->enable_auto_interception();
    }
    
    public function get_api_key(): string {
        return $this->encryption->get_option( 'api_key' );
    }
}
```

## Security Considerations

This library:

* Uses AES-256-GCM, an *authenticated* cipher: ciphertext that has been
  altered in the database fails to decrypt rather than silently yielding
  attacker-influenced plaintext
* Automatically generates a fresh random IV for each encryption
* Reads values written by <= 1.0.0 (unauthenticated AES-256-CBC) so upgrades
  are seamless; re-saving a value rewrites it in the authenticated format.
  `is_legacy_format()` identifies values still needing that migration
* Uses WordPress salts and auth keys for enhanced security by default
* Validates that the OpenSSL extension is available
* Returns WordPress-style error responses for graceful failure handling
* Supports dedicated encryption keys via `WP_ENCRYPTION_KEY` constant

## Error Handling

The library uses standard WordPress error handling:

```php
$encrypted = $encryption->encrypt( 'sensitive-data' );
if ( is_wp_error( $encrypted ) ) {
    $error_message = $encrypted->get_error_message();
    error_log( 'Encryption error: ' . $error_message );
    return false;
}
```

## Architecture

The library uses a trait-based architecture for clean separation of concerns:

* **Build** - String/name building utilities
* **Core** - Basic encryption/decryption functionality
* **Constants** - WordPress constants handling
* **Options** - WordPress options with encryption
* **AutoIntercept** - Automatic get_option interception
* **Transients** - WordPress transients with encryption
* **UserMeta** - User meta with encryption
* **PostMeta** - Post meta with encryption

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

Licensed under the GPLv2 or later license.

## Support

- [Issue Tracker](https://github.com/arraypress/wp-encryption/issues)
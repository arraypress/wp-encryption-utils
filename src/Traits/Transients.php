<?php
/**
 * Transients Trait
 *
 * @package     ArrayPress\WP\Encryption\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Traits;

trait Transients {

	/**
	 * Set a WordPress transient with an encrypted value
	 *
	 * @param string $transient  Transient name
	 * @param string $value      Value to encrypt and store
	 * @param int    $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
	 *
	 * @return bool Whether the transient was set successfully
	 */
	public function set_transient( string $transient, string $value, int $expiration = 0 ): bool {
		$encrypted = $this->encrypt( $value );

		// Handle encryption errors
		if ( is_wp_error( $encrypted ) ) {
			return false;
		}

		return set_transient( $transient, $encrypted, $expiration );
	}

	/**
	 * Get and decrypt a WordPress transient
	 *
	 * @param string $transient Transient name
	 *
	 * @return string|false Decrypted transient value or false if not found
	 */
	public function get_transient( string $transient ) {
		$value = get_transient( $transient );

		if ( $value === false || ! is_string( $value ) ) {
			return false;
		}

		$decrypted = $this->decrypt( $value );

		// Handle decryption errors
		if ( is_wp_error( $decrypted ) ) {
			return false;
		}

		return $decrypted;
	}
}

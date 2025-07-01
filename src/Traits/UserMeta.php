<?php
/**
 * UserMeta Trait
 *
 * @package     ArrayPress\WP\Encryption\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Traits;

trait UserMeta {

	/**
	 * Update user meta with an encrypted value
	 *
	 * @param int    $user_id    User ID
	 * @param string $meta_key   Meta key
	 * @param string $meta_value Meta value to encrypt
	 *
	 * @return int|bool Meta ID on success, false on failure
	 */
	public function update_user_meta( int $user_id, string $meta_key, string $meta_value ) {
		$encrypted = $this->encrypt( $meta_value );

		// Handle encryption errors
		if ( is_wp_error( $encrypted ) ) {
			return false;
		}

		return update_user_meta( $user_id, $meta_key, $encrypted );
	}

	/**
	 * Get and decrypt user meta
	 *
	 * @param int    $user_id  User ID
	 * @param string $meta_key Meta key
	 * @param string $default  Default value if meta doesn't exist
	 *
	 * @return string Decrypted user meta value
	 */
	public function get_user_meta( int $user_id, string $meta_key, string $default = '' ): string {
		$value = get_user_meta( $user_id, $meta_key, true );

		if ( ! is_string( $value ) ) {
			return $default;
		}

		$decrypted = $this->decrypt( $value );

		// Handle decryption errors
		if ( is_wp_error( $decrypted ) ) {
			return $default;
		}

		return $decrypted;
	}

}
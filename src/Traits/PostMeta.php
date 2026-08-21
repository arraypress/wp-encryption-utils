<?php
/**
 * PostMeta Trait
 *
 * @package     ArrayPress\WP\Encryption\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Traits;

trait PostMeta {

	/**
	 * Update post meta with encrypted value
	 *
	 * @param int    $post_id    Post ID
	 * @param string $meta_key   Meta key
	 * @param string $meta_value Meta value to encrypt
	 *
	 * @return int|bool Meta ID on success, false on failure
	 */
	public function update_post_meta( int $post_id, string $meta_key, string $meta_value ) {
		$encrypted = $this->encrypt( $meta_value );

		// Handle encryption errors
		if ( is_wp_error( $encrypted ) ) {
			return false;
		}

		return update_post_meta( $post_id, $meta_key, $encrypted );
	}

	/**
	 * Get and decrypt post meta
	 *
	 * @param int    $post_id  Post ID
	 * @param string $meta_key Meta key
	 * @param string $default  Default value if meta doesn't exist
	 *
	 * @return string Decrypted post meta value
	 */
	public function get_post_meta( int $post_id, string $meta_key, string $default = '' ): string {
		$value = get_post_meta( $post_id, $meta_key, true );

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

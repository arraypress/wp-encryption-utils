<?php
/**
 * Options Trait
 *
 * @package     ArrayPress\WP\Encryption\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Traits;

trait Options {

	/**
	 * Convert option name to full option name with prefix
	 *
	 * @param string $option_name Option name (e.g., 'account_id')
	 *
	 * @return string Full option name (e.g., 'wc_r2_account_id')
	 */
	private function get_full_option_name( string $option_name ): string {
		if ( ! empty( $this->prefix_name ) ) {
			return $this->prefix_name . $option_name;
		}

		return $option_name;
	}

	/**
	 * Update a WordPress option with an encrypted value
	 * Will not update if a constant is defined for this option.
	 *
	 * @param string $option Option name (without prefix)
	 * @param string $value  Value to encrypt and store
	 *
	 * @return bool Whether the option was updated successfully
	 */
	public function update_option( string $option, string $value ): bool {
		// Don't save to the database when the value is fixed by configuration:
		// the write would succeed but get_option() would keep returning the
		// constant or environment value, which reads as data loss.
		if ( $this->is_externally_defined( $option ) ) {
			return false;
		}

		$encrypted = $this->encrypt( $value );

		// Handle encryption errors
		if ( is_wp_error( $encrypted ) ) {
			return false;
		}

		$full_option_name = $this->get_full_option_name( $option );

		// Track this option for auto-interception if enabled
		if ( $this->auto_intercept ) {
			$this->track_option( $option );
		}

		return update_option( $full_option_name, $encrypted );
	}

	/**
	 * Get and decrypt a WordPress option
	 * Automatically checks for constants first.
	 *
	 * @param string $option  Option name (without prefix)
	 * @param string $default Default value if option doesn't exist
	 *
	 * @return string Decrypted option value or default if error
	 */
	public function get_option( string $option, string $default = '' ): string {
		// Precedence: constant, then environment variable, then the encrypted
		// database option. Constants win because they are explicit and local to
		// the site; env is the fallback for hosts where wp-config.php cannot be
		// written.
		$constant_value = $this->get_constant_for_option( $option );
		if ( $constant_value !== null ) {
			return $constant_value;
		}

		$env_value = $this->get_env_for_option( $option );
		if ( $env_value !== null ) {
			return $env_value;
		}

		// Fall back to database option
		$full_option_name = $this->get_full_option_name( $option );
		$value            = get_option( $full_option_name, $default );

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

	/**
	 * Delete a WordPress option
	 * Will not delete if a constant is defined for this option unless forced.
	 *
	 * @param string $option Option name (without prefix)
	 * @param bool   $force  Whether to force deletion even if constant is defined
	 *
	 * @return bool Whether the option was deleted successfully
	 */
	public function delete_option( string $option, bool $force = false ): bool {
		if ( ! $force && $this->is_externally_defined( $option ) ) {
			return false;
		}

		$full_option_name = $this->get_full_option_name( $option );

		return delete_option( $full_option_name );
	}

	/**
	 * Get option info including source
	 *
	 * @param string $option  Option name (without prefix)
	 * @param string $default Default value
	 *
	 * @return array Array with 'value', 'source', and additional info
	 */
	public function get_option_info( string $option, string $default = '' ): array {
		// Check constant first
		$constant_value = $this->get_constant_for_option( $option );
		if ( $constant_value !== null ) {
			return [
				'value'        => $constant_value,
				'source'       => 'constant',
				'constant'     => $this->option_to_constant( $option ),
				'is_encrypted' => false,
			];
		}

		// Check environment variable
		$env_value = $this->get_env_for_option( $option );
		if ( $env_value !== null ) {
			return [
				'value'        => $env_value,
				'source'       => 'env',
				'env'          => $this->option_to_env( $option ),
				'is_encrypted' => false,
			];
		}

		// Check database option
		$full_option_name = $this->get_full_option_name( $option );
		$option_value     = get_option( $full_option_name, null );
		if ( $option_value !== null ) {
			$decrypted = $this->decrypt( $option_value );
			if ( ! is_wp_error( $decrypted ) ) {
				return [
					'value'        => $decrypted,
					'source'       => 'database',
					'option'       => $full_option_name,
					'is_encrypted' => $this->is_encrypted( $option_value ),
				];
			}
		}

		// Return default
		return [
			'value'        => $default,
			'source'       => 'default',
			'is_encrypted' => false,
		];
	}

}
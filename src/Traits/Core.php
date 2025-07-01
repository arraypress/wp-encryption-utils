<?php
/**
 * Core Encryption Trait
 *
 * @package     ArrayPress\WP\Encryption\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Traits;

use Exception;
use RuntimeException;
use WP_Error;

trait Core {

	/**
	 * Encryption algorithm to use
	 *
	 * @var string
	 */
	private string $algorithm = 'aes-256-cbc';

	/**
	 * Encryption key
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Prefix for encrypted values
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Encrypt a value
	 *
	 * @param string $value Value to encrypt
	 *
	 * @return string|WP_Error Encrypted value with prefix or WP_Error on failure
	 */
	public function encrypt( string $value ) {
		if ( empty( $value ) ) {
			return $value;
		}

		try {
			$iv = random_bytes( openssl_cipher_iv_length( $this->algorithm ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'encryption_error', 'Failed to generate IV: ' . $e->getMessage() );
		}

		$encrypted = openssl_encrypt( $value, $this->algorithm, $this->key, OPENSSL_RAW_DATA, $iv );

		if ( $encrypted === false ) {
			return new WP_Error( 'encryption_error', 'Encryption failed: ' . openssl_error_string() );
		}

		return $this->prefix . base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a value
	 *
	 * @param string $value Value to decrypt (with or without prefix)
	 *
	 * @return string|WP_Error Decrypted value or WP_Error on failure
	 */
	public function decrypt( string $value ) {
		if ( empty( $value ) ) {
			return $value;
		}

		if ( ! $this->is_encrypted( $value ) ) {
			return $value;
		}

		$encrypted_data = substr( $value, strlen( $this->prefix ) );
		$data           = base64_decode( $encrypted_data );

		if ( $data === false ) {
			return new WP_Error( 'decryption_error', 'Invalid encrypted data' );
		}

		$iv_length = openssl_cipher_iv_length( $this->algorithm );
		$iv        = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );

		$decrypted = openssl_decrypt( $encrypted, $this->algorithm, $this->key, OPENSSL_RAW_DATA, $iv );

		if ( $decrypted === false ) {
			return new WP_Error( 'decryption_error', 'Decryption failed: ' . openssl_error_string() );
		}

		return $decrypted;
	}

	/**
	 * Check if a value is encrypted
	 *
	 * @param string $value Value to check
	 *
	 * @return bool Whether the value is encrypted
	 */
	public function is_encrypted( string $value ): bool {
		return strpos( $value, $this->prefix ) === 0;
	}

	/**
	 * Change the encryption key
	 *
	 * @param string|null $key New encryption key. If null, regenerates from WordPress salts.
	 *
	 * @return void
	 */
	public function change_key( ?string $key = null ): void {
		$this->key = $key ? hash( 'sha256', $key, true ) : $this->get_wordpress_key();
	}

	/**
	 * Get the WordPress-based encryption key
	 *
	 * @return string WordPress-derived encryption key
	 * @throws RuntimeException If no key source is available
	 */
	private function get_wordpress_key(): string {
		if ( defined( 'WP_ENCRYPTION_KEY' ) && ! empty( constant( 'WP_ENCRYPTION_KEY' ) ) ) {
			return hash( 'sha256', constant( 'WP_ENCRYPTION_KEY' ), true );
		}

		$salts = [
			defined( 'AUTH_KEY' ) ? AUTH_KEY : '',
			defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '',
			defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '',
			defined( 'NONCE_KEY' ) ? NONCE_KEY : '',
		];

		$combined = implode( '', $salts );

		if ( empty( $combined ) && function_exists( 'wp_salt' ) ) {
			$combined = wp_salt() . wp_salt( 'secure_auth' );
		}

		if ( empty( $combined ) ) {
			throw new RuntimeException( 'Cannot generate encryption key: WordPress salts not available. Consider defining WP_ENCRYPTION_KEY.' );
		}

		return hash( 'sha256', $combined, true );
	}

	/**
	 * Validate that the environment supports encryption
	 *
	 * @return void
	 * @throws RuntimeException If OpenSSL is not available
	 */
	private function validate_environment(): void {
		if ( ! extension_loaded( 'openssl' ) ) {
			throw new RuntimeException( 'OpenSSL extension is required for encryption' );
		}

		if ( ! in_array( $this->algorithm, openssl_get_cipher_methods(), true ) ) {
			throw new RuntimeException( "Encryption algorithm '{$this->algorithm}' is not supported" );
		}
	}

}
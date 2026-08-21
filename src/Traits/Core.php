<?php
/**
 * Core Encryption Trait
 *
 * @package     ArrayPress\WP\Encryption\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.1.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Traits;

use Exception;
use RuntimeException;
use WP_Error;

trait Core {

	/**
	 * Authenticated cipher used for all new ciphertexts.
	 *
	 * @var string
	 */
	private string $algorithm = 'aes-256-gcm';

	/**
	 * Unauthenticated cipher retained only to read values written by <= 1.0.0.
	 *
	 * @var string
	 */
	private string $legacy_algorithm = 'aes-256-cbc';

	/**
	 * Marker distinguishing the authenticated format from the legacy one.
	 *
	 * Base64 never produces ':', so a payload starting with this cannot be
	 * mistaken for a legacy IV.
	 *
	 * @var string
	 */
	private string $format_marker = 'v2:';

	/**
	 * GCM initialisation vector length in bytes. 96 bits is the value GCM is
	 * specified around; other lengths take a slower, weaker construction.
	 */
	private const GCM_IV_LENGTH = 12;

	/**
	 * GCM authentication tag length in bytes.
	 */
	private const GCM_TAG_LENGTH = 16;

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
	 * Uses AES-256-GCM, which authenticates as well as encrypts. The previous
	 * AES-256-CBC construction had no MAC, so ciphertext sitting in wp_options
	 * could be altered by anyone with database access -- or via SQL injection
	 * in any other plugin on the site -- and would decrypt to attacker-chosen
	 * plaintext without the tampering being detectable. For a library whose
	 * whole job is holding API keys and tokens, that mattered.
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
			$iv = random_bytes( self::GCM_IV_LENGTH );
		} catch ( Exception $e ) {
			return new WP_Error( 'encryption_error', 'Failed to generate IV: ' . $e->getMessage() );
		}

		$tag       = '';
		$encrypted = openssl_encrypt(
			$value,
			$this->algorithm,
			$this->key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::GCM_TAG_LENGTH
		);

		if ( false === $encrypted ) {
			return new WP_Error( 'encryption_error', 'Encryption failed: ' . openssl_error_string() );
		}

		return $this->prefix . $this->format_marker . base64_encode( $iv . $tag . $encrypted );
	}

	/**
	 * Decrypt a value
	 *
	 * Reads both the authenticated format and the legacy CBC format, so values
	 * written by earlier versions keep working. Anything re-saved is written
	 * back in the authenticated format.
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

		$payload = substr( $value, strlen( $this->prefix ) );

		if ( str_starts_with( $payload, $this->format_marker ) ) {
			return $this->decrypt_authenticated( substr( $payload, strlen( $this->format_marker ) ) );
		}

		return $this->decrypt_legacy( $payload );
	}

	/**
	 * Decrypt an AES-256-GCM payload
	 *
	 * @param string $payload Base64 payload of iv|tag|ciphertext
	 *
	 * @return string|WP_Error
	 */
	private function decrypt_authenticated( string $payload ) {
		$data = base64_decode( $payload, true );

		if ( false === $data || strlen( $data ) <= self::GCM_IV_LENGTH + self::GCM_TAG_LENGTH ) {
			return new WP_Error( 'decryption_error', 'Invalid encrypted data' );
		}

		$iv         = substr( $data, 0, self::GCM_IV_LENGTH );
		$tag        = substr( $data, self::GCM_IV_LENGTH, self::GCM_TAG_LENGTH );
		$ciphertext = substr( $data, self::GCM_IV_LENGTH + self::GCM_TAG_LENGTH );

		$decrypted = openssl_decrypt( $ciphertext, $this->algorithm, $this->key, OPENSSL_RAW_DATA, $iv, $tag );

		// A false here means the tag did not verify: the ciphertext was
		// truncated, corrupted, or deliberately modified. Report it rather than
		// returning anything derived from it.
		if ( false === $decrypted ) {
			return new WP_Error( 'decryption_error', 'Decryption failed: data failed authentication' );
		}

		return $decrypted;
	}

	/**
	 * Decrypt a legacy AES-256-CBC payload written by version <= 1.0.0
	 *
	 * Kept for backwards compatibility only. These values carry no
	 * authentication tag, so tampering cannot be detected -- re-save them to
	 * upgrade them to the authenticated format.
	 *
	 * @param string $payload Base64 payload of iv|ciphertext
	 *
	 * @return string|WP_Error
	 */
	private function decrypt_legacy( string $payload ) {
		$data = base64_decode( $payload, true );

		if ( false === $data ) {
			return new WP_Error( 'decryption_error', 'Invalid encrypted data' );
		}

		$iv_length = (int) openssl_cipher_iv_length( $this->legacy_algorithm );

		if ( strlen( $data ) <= $iv_length ) {
			return new WP_Error( 'decryption_error', 'Invalid encrypted data' );
		}

		$decrypted = openssl_decrypt(
			substr( $data, $iv_length ),
			$this->legacy_algorithm,
			$this->key,
			OPENSSL_RAW_DATA,
			substr( $data, 0, $iv_length )
		);

		if ( false === $decrypted ) {
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
		return str_starts_with( $value, $this->prefix );
	}

	/**
	 * Check whether a stored value still uses the legacy unauthenticated format
	 *
	 * Useful for a one-off migration: read each value, and if this returns
	 * true, write it straight back to re-encrypt it under AES-256-GCM.
	 *
	 * @param string $value Stored value
	 *
	 * @return bool
	 */
	public function is_legacy_format( string $value ): bool {
		return $this->is_encrypted( $value )
		       && ! str_starts_with( substr( $value, strlen( $this->prefix ) ), $this->format_marker );
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

		$available = openssl_get_cipher_methods();

		if ( ! in_array( $this->algorithm, $available, true ) ) {
			throw new RuntimeException( "Encryption algorithm '{$this->algorithm}' is not supported" );
		}
	}

}

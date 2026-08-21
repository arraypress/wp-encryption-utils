<?php
declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Tests;

use ArrayPress\EncryptionUtils\Manager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Error;

/**
 * Authenticated encryption.
 */
final class CryptoTest extends TestCase {

	private Manager $manager;

	protected function setUp(): void {
		$GLOBALS['wp_test_options'] = [];
		$this->manager              = new Manager( 'testpfx', 'unit-test-key', false );
	}

	/**
	 * Write a value in the pre-1.1.0 unauthenticated CBC format.
	 */
	private function encrypt_legacy( string $value ): string {
		$ref = new ReflectionClass( $this->manager );

		$key    = $ref->getProperty( 'key' )->getValue( $this->manager );
		$prefix = $ref->getProperty( 'prefix' )->getValue( $this->manager );

		$iv = random_bytes( 16 );

		return $prefix . base64_encode(
			$iv . openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv )
		);
	}

	public function test_round_trip(): void {
		$secret = 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';

		$this->assertSame( $secret, $this->manager->decrypt( $this->manager->encrypt( $secret ) ) );
	}

	public function test_ciphertext_uses_the_authenticated_format(): void {
		$this->assertStringContainsString( 'v2:', $this->manager->encrypt( 'x' ) );
	}

	public function test_each_encryption_uses_a_fresh_iv(): void {
		$this->assertNotSame( $this->manager->encrypt( 'same' ), $this->manager->encrypt( 'same' ) );
	}

	public function test_empty_value_passes_through(): void {
		$this->assertSame( '', $this->manager->encrypt( '' ) );
		$this->assertSame( '', $this->manager->decrypt( '' ) );
	}

	public function test_plaintext_is_returned_unchanged(): void {
		$this->assertSame( 'not-encrypted', $this->manager->decrypt( 'not-encrypted' ) );
	}

	/**
	 * The reason for moving off CBC: without a tag, a modified ciphertext
	 * decrypts to attacker-influenced plaintext with nothing to signal it.
	 */
	public function test_tampered_ciphertext_is_rejected(): void {
		$encrypted = $this->manager->encrypt( 'super-secret-value' );

		$marker  = strpos( $encrypted, 'v2:' ) + 3;
		$payload = base64_decode( substr( $encrypted, $marker ) );

		// Flip one bit of the ciphertext body.
		$payload[ strlen( $payload ) - 1 ] = chr( ord( $payload[ strlen( $payload ) - 1 ] ) ^ 0x01 );

		$tampered = substr( $encrypted, 0, $marker ) . base64_encode( $payload );

		$this->assertInstanceOf( WP_Error::class, $this->manager->decrypt( $tampered ) );
	}

	public function test_truncated_payload_is_rejected(): void {
		$encrypted = $this->manager->encrypt( 'value' );
		$marker    = strpos( $encrypted, 'v2:' ) + 3;
		$truncated = substr( $encrypted, 0, $marker ) . base64_encode( 'short' );

		$this->assertInstanceOf( WP_Error::class, $this->manager->decrypt( $truncated ) );
	}

	public function test_non_base64_payload_is_rejected(): void {
		$encrypted = $this->manager->encrypt( 'value' );
		$marker    = strpos( $encrypted, 'v2:' ) + 3;

		$this->assertInstanceOf(
			WP_Error::class,
			$this->manager->decrypt( substr( $encrypted, 0, $marker ) . 'not base64 !!!' )
		);
	}

	public function test_legacy_cbc_values_still_decrypt(): void {
		$legacy = $this->encrypt_legacy( 'written-by-1.0.0' );

		$this->assertSame( 'written-by-1.0.0', $this->manager->decrypt( $legacy ) );
	}

	public function test_legacy_values_are_identifiable_for_migration(): void {
		$this->assertTrue( $this->manager->is_legacy_format( $this->encrypt_legacy( 'old' ) ) );
		$this->assertFalse( $this->manager->is_legacy_format( $this->manager->encrypt( 'new' ) ) );
	}

	public function test_resaving_upgrades_a_legacy_value(): void {
		$GLOBALS['wp_test_options']['testpfx_api_key'] = $this->encrypt_legacy( 'old-value' );

		$this->assertTrue( $this->manager->is_legacy_format( $GLOBALS['wp_test_options']['testpfx_api_key'] ) );
		$this->assertSame( 'old-value', $this->manager->get_option( 'api_key' ) );

		$this->manager->update_option( 'api_key', 'old-value' );

		$this->assertFalse( $this->manager->is_legacy_format( $GLOBALS['wp_test_options']['testpfx_api_key'] ) );
		$this->assertSame( 'old-value', $this->manager->get_option( 'api_key' ) );
	}
}

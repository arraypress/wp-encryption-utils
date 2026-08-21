<?php
declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Tests;

use ArrayPress\EncryptionUtils\Manager;
use PHPUnit\Framework\TestCase;

/**
 * Configuration source precedence: constant, then environment, then database.
 */
final class PrecedenceTest extends TestCase {

	private Manager $manager;

	protected function setUp(): void {
		$GLOBALS['wp_test_options'] = [];
		putenv( 'TESTPFX_API_KEY' );
		// auto_intercept off: it registers WordPress filters we are not exercising.
		$this->manager = new Manager( 'testpfx', 'unit-test-key', false );
	}

	protected function tearDown(): void {
		putenv( 'TESTPFX_API_KEY' );
		$GLOBALS['wp_test_options'] = [];
	}

	public function test_database_value_is_used_when_nothing_else_is_set(): void {
		$this->manager->update_option( 'api_key', 'from-database' );

		$this->assertSame( 'from-database', $this->manager->get_option( 'api_key' ) );
	}

	public function test_stored_value_is_encrypted_at_rest(): void {
		$this->manager->update_option( 'api_key', 'super-secret' );

		$raw = $GLOBALS['wp_test_options']['testpfx_api_key'];

		$this->assertNotSame( 'super-secret', $raw );
		$this->assertStringNotContainsString( 'super-secret', $raw );
		$this->assertTrue( $this->manager->is_encrypted( $raw ) );
	}

	public function test_environment_variable_overrides_the_database(): void {
		$this->manager->update_option( 'api_key', 'from-database' );
		putenv( 'TESTPFX_API_KEY=from-environment' );

		$this->assertSame( 'from-environment', $this->manager->get_option( 'api_key' ) );
	}

	public function test_empty_environment_variable_is_ignored(): void {
		$this->manager->update_option( 'api_key', 'from-database' );
		putenv( 'TESTPFX_API_KEY=' );

		$this->assertSame( 'from-database', $this->manager->get_option( 'api_key' ) );
	}

	public function test_env_is_reported_as_defined(): void {
		$this->assertFalse( $this->manager->is_env_defined( 'api_key' ) );

		putenv( 'TESTPFX_API_KEY=x' );

		$this->assertTrue( $this->manager->is_env_defined( 'api_key' ) );
		$this->assertSame( 'TESTPFX_API_KEY', $this->manager->get_env_name( 'api_key' ) );
	}

	/**
	 * Writing while an environment variable is set would succeed silently while
	 * get_option() kept returning the env value — indistinguishable from data
	 * loss, so the write is refused instead.
	 */
	public function test_write_is_refused_while_the_environment_supplies_the_value(): void {
		putenv( 'TESTPFX_API_KEY=from-environment' );

		$this->assertFalse( $this->manager->update_option( 'api_key', 'attempted' ) );
		$this->assertSame( 'from-environment', $this->manager->get_option( 'api_key' ) );
	}

	public function test_delete_is_refused_while_the_environment_supplies_the_value(): void {
		$this->manager->update_option( 'api_key', 'from-database' );
		putenv( 'TESTPFX_API_KEY=from-environment' );

		$this->assertFalse( $this->manager->delete_option( 'api_key' ) );
		$this->assertTrue( $this->manager->delete_option( 'api_key', true ) );
	}

	public function test_option_info_reports_the_source(): void {
		$this->manager->update_option( 'api_key', 'from-database' );
		$this->assertSame( 'database', $this->manager->get_option_info( 'api_key' )['source'] );

		putenv( 'TESTPFX_API_KEY=from-environment' );
		$info = $this->manager->get_option_info( 'api_key' );

		$this->assertSame( 'env', $info['source'] );
		$this->assertSame( 'TESTPFX_API_KEY', $info['env'] );
		$this->assertFalse( $info['is_encrypted'] );
	}

	public function test_missing_option_returns_the_default(): void {
		$this->assertSame( 'fallback', $this->manager->get_option( 'nope', 'fallback' ) );
	}

	public function test_externally_defined_covers_env(): void {
		$this->assertFalse( $this->manager->is_externally_defined( 'api_key' ) );

		putenv( 'TESTPFX_API_KEY=x' );

		$this->assertTrue( $this->manager->is_externally_defined( 'api_key' ) );
	}
}

<?php
/**
 * Test bootstrap.
 *
 * Stubs the small slice of WordPress this library touches, so the suite runs
 * without a WordPress install or a database. Options live in an in-memory
 * array that tests can reset.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['wp_test_options'] = [];

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code;
		public string $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		return $GLOBALS['wp_test_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $name, $value, $autoload = null ): bool {
		$GLOBALS['wp_test_options'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $name ): bool {
		unset( $GLOBALS['wp_test_options'][ $name ] );

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ): bool {
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( ...$args ): bool {
		return true;
	}
}

if ( ! function_exists( 'current_filter' ) ) {
	function current_filter(): string {
		return '';
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( string $scheme = 'auth' ): string {
		return 'test-salt-' . $scheme;
	}
}

require_once __DIR__ . '/../vendor/autoload.php';

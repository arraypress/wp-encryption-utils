<?php
/**
 * Build Trait
 *
 * @package     ArrayPress\WP\Encryption\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Traits;

trait Build {

	/**
	 * Build option prefix with underscore
	 *
	 * @param string $prefix Base prefix
	 *
	 * @return string Option prefix with underscore
	 */
	private function build_option_prefix( string $prefix ): string {
		return $prefix . '_';
	}

	/**
	 * Build encryption prefix for encrypted values
	 *
	 * @param string $prefix Base prefix
	 *
	 * @return string Encryption prefix
	 */
	private function build_encryption_prefix( string $prefix ): string {
		return '__' . strtoupper( $prefix ) . '_ENCRYPTED__';
	}

	/**
	 * Build constant name from option name
	 *
	 * @param string $option_name Full option name
	 *
	 * @return string Constant name in uppercase
	 */
	private function build_constant_name( string $option_name ): string {
		return strtoupper( $option_name );
	}

	/**
	 * Build full filter hook name for option interception
	 *
	 * @param string $option_name Full option name
	 *
	 * @return string Filter hook name
	 */
	private function build_filter_hook( string $option_name ): string {
		return "pre_option_{$option_name}";
	}

}
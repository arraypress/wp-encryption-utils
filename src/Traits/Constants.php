<?php
/**
 * Constants Trait
 *
 * @package     ArrayPress\WP\Encryption\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Traits;

trait Constants {

	/**
	 * Prefix for option and constant names
	 *
	 * @var string
	 */
	private string $prefix_name;

	/**
	 * Convert option name to constant name
	 *
	 * @param string $option_name Option name (e.g., 'account_id')
	 *
	 * @return string Constant name (e.g., 'WC_R2_ACCOUNT_ID')
	 */
	private function option_to_constant( string $option_name ): string {
		$full_option_name = $this->get_full_option_name( $option_name );

		return $this->build_constant_name( $full_option_name );
	}

	/**
	 * Check if a constant exists for an option
	 *
	 * @param string $option_name Option name
	 *
	 * @return bool Whether the constant is defined and not empty
	 */
	private function has_constant_for_option( string $option_name ): bool {
		$constant_name = $this->option_to_constant( $option_name );

		return defined( $constant_name ) && ! empty( constant( $constant_name ) );
	}

	/**
	 * Get constant value for an option
	 *
	 * @param string $option_name Option name
	 *
	 * @return string|null Constant value or null if not defined
	 */
	private function get_constant_for_option( string $option_name ): ?string {
		$constant_name = $this->option_to_constant( $option_name );

		if ( defined( $constant_name ) && ! empty( constant( $constant_name ) ) ) {
			return constant( $constant_name );
		}

		return null;
	}

	/**
	 * Check if an option is defined as a constant
	 *
	 * @param string $option Option name (without prefix)
	 *
	 * @return bool Whether the option has a constant defined
	 */
	public function is_constant_defined( string $option ): bool {
		return $this->has_constant_for_option( $option );
	}

	/**
	 * Get the constant name for an option
	 *
	 * @param string $option Option name (without prefix)
	 *
	 * @return string Constant name that would be checked
	 */
	public function get_constant_name( string $option ): string {
		return $this->option_to_constant( $option );
	}

	/**
	 * Generate setting description for admin interfaces
	 *
	 * @param string $option    Option name (without prefix)
	 * @param string $base_desc Base description text
	 *
	 * @return string Enhanced description with constant information
	 */
	public function get_setting_description( string $option, string $base_desc ): string {
		if ( $this->is_constant_defined( $option ) ) {
			return $base_desc . sprintf(
					' <strong>%s</strong> <code>%s</code>',
					__( 'Defined as constant:', 'arraypress' ),
					$this->get_constant_name( $option )
				);
		}

		// Say so when an environment variable is supplying the value, otherwise
		// the field looks editable while saves silently do nothing.
		if ( $this->is_env_defined( $option ) ) {
			return $base_desc . sprintf(
					' <strong>%s</strong> <code>%s</code>',
					__( 'Defined as environment variable:', 'arraypress' ),
					$this->get_env_name( $option )
				);
		}

		return $base_desc . ' ' . __( '(stored encrypted in database)', 'arraypress' );
	}
}

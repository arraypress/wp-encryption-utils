<?php
/**
 * AutoIntercept Trait
 *
 * @package     ArrayPress\WP\Encryption\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Traits;

trait AutoIntercept {

	/**
	 * Whether to auto-intercept get_option calls
	 *
	 * @var bool
	 */
	private bool $auto_intercept;

	/**
	 * Tracked option names for auto-interception
	 *
	 * @var array
	 */
	private array $tracked_options = [];

	/**
	 * Setup automatic interception of get_option calls
	 *
	 * @return void
	 */
	private function setup_auto_interception(): void {
		// This will be called when options are tracked
	}

	/**
	 * Track an option for auto-interception
	 *
	 * @param string $option Option name (without prefix)
	 *
	 * @return void
	 */
	public function track_option( string $option ): void {
		if ( ! in_array( $option, $this->tracked_options, true ) ) {
			$this->tracked_options[] = $option;
			$full_option_name        = $this->get_full_option_name( $option );
			$filter_hook             = $this->build_filter_hook( $full_option_name );
			add_filter( $filter_hook, [ $this, 'intercept_option_value' ] );
		}
	}

	/**
	 * Intercept option values to return decrypted data
	 *
	 * @param mixed $value The option value
	 *
	 * @return mixed
	 */
	public function intercept_option_value( $value ) {
		// Get the option name from the current filter
		$full_option_name = str_replace( 'pre_option_', '', current_filter() );

		// Extract the base name (remove prefix)
		if ( ! empty( $this->prefix_name ) ) {
			$base_name = str_replace( $this->prefix_name, '', $full_option_name );
		} else {
			$base_name = $full_option_name;
		}

		// Temporarily remove our filter to prevent infinite loop
		remove_filter( current_filter(), [ $this, 'intercept_option_value' ] );

		// Get the decrypted value using our method
		$decrypted_value = $this->get_option( $base_name, '' );

		// Re-add our filter
		add_filter( current_filter(), [ $this, 'intercept_option_value' ] );

		return $decrypted_value;
	}

	/**
	 * Enable auto-interception for existing tracked options
	 *
	 * @return void
	 */
	public function enable_auto_interception(): void {
		$this->auto_intercept = true;

		// Set up filters for already tracked options
		foreach ( $this->tracked_options as $option ) {
			$full_option_name = $this->get_full_option_name( $option );
			$filter_hook      = $this->build_filter_hook( $full_option_name );
			add_filter( $filter_hook, [ $this, 'intercept_option_value' ] );
		}
	}

	/**
	 * Disable auto-interception
	 *
	 * @return void
	 */
	public function disable_auto_interception(): void {
		$this->auto_intercept = false;

		// Remove filters for tracked options
		foreach ( $this->tracked_options as $option ) {
			$full_option_name = $this->get_full_option_name( $option );
			$filter_hook      = $this->build_filter_hook( $full_option_name );
			remove_filter( $filter_hook, [ $this, 'intercept_option_value' ] );
		}
	}

	/**
	 * Check if auto-interception is currently enabled
	 *
	 * @return bool Whether auto-interception is enabled
	 */
	public function is_auto_intercept_enabled(): bool {
		return $this->auto_intercept;
	}

}
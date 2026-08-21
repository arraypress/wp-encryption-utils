<?php
/**
 * Environment Variable Trait
 *
 * @package     ArrayPress\WP\Encryption\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.2.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils\Traits;

/**
 * Trait Env
 *
 * Reads configuration from environment variables, for hosts that inject
 * secrets rather than letting you write them into wp-config.php — containers,
 * Kubernetes, Platform.sh, Pantheon, and most CI pipelines.
 *
 * Environment variables sit *below* constants in precedence, deliberately.
 * A constant in wp-config.php is explicit, local to the site, and cannot be
 * read out of the process table; an environment variable is more easily
 * exposed by phpinfo(), a stack trace, or a debugging plugin. Constants stay
 * the recommended mechanism, and env is the fallback for the platforms where
 * writing a file is not an option.
 */
trait Env {

	/**
	 * Convert an option name to its environment variable name
	 *
	 * Uses the same derivation as constants, so WP_ENCRYPTION_KEY-style naming
	 * carries across: an option of 'account_id' under prefix 'wc_r2' becomes
	 * WC_R2_ACCOUNT_ID either way.
	 *
	 * @param string $option_name Option name (e.g. 'account_id').
	 *
	 * @return string Environment variable name (e.g. 'WC_R2_ACCOUNT_ID').
	 */
	private function option_to_env( string $option_name ): string {
		return $this->option_to_constant( $option_name );
	}

	/**
	 * Read an environment variable
	 *
	 * Uses getenv() rather than $_ENV. PHP's variables_order defaults to
	 * "GPCS" — no E — so $_ENV is empty on most stock installations, while
	 * getenv() consults the real environment regardless.
	 *
	 * Note for PHP-FPM: the default pool setting is clear_env = yes, which
	 * strips the environment before workers start. Variables exported in a
	 * shell or Dockerfile will NOT reach PHP until the pool passes them
	 * explicitly with env[NAME] = $NAME, or sets clear_env = no. This is the
	 * usual reason an otherwise correct configuration appears to be ignored.
	 *
	 * @param string $name Environment variable name.
	 *
	 * @return string|null Value, or null when unset or empty.
	 */
	private function read_env( string $name ): ?string {
		$value = getenv( $name );

		if ( false === $value || '' === $value ) {
			return null;
		}

		return $value;
	}

	/**
	 * Check whether an environment variable exists for an option
	 *
	 * @param string $option_name Option name.
	 *
	 * @return bool
	 */
	private function has_env_for_option( string $option_name ): bool {
		return null !== $this->read_env( $this->option_to_env( $option_name ) );
	}

	/**
	 * Get the environment variable value for an option
	 *
	 * @param string $option_name Option name.
	 *
	 * @return string|null Value, or null if unset.
	 */
	private function get_env_for_option( string $option_name ): ?string {
		return $this->read_env( $this->option_to_env( $option_name ) );
	}

	/**
	 * Check whether an option is supplied by an environment variable
	 *
	 * @param string $option Option name (without prefix).
	 *
	 * @return bool
	 */
	public function is_env_defined( string $option ): bool {
		return $this->has_env_for_option( $option );
	}

	/**
	 * Get the environment variable name for an option
	 *
	 * Useful for settings screens: show the admin which variable to set.
	 *
	 * @param string $option Option name (without prefix).
	 *
	 * @return string
	 */
	public function get_env_name( string $option ): string {
		return $this->option_to_env( $option );
	}

	/**
	 * Check whether an option is fixed by configuration rather than the database
	 *
	 * True when either a constant or an environment variable supplies the
	 * value, in which case the settings UI should render the field read-only:
	 * saving it would silently have no effect.
	 *
	 * @param string $option Option name (without prefix).
	 *
	 * @return bool
	 */
	public function is_externally_defined( string $option ): bool {
		return $this->has_constant_for_option( $option ) || $this->has_env_for_option( $option );
	}

}

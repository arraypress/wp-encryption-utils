<?php
/**
 * Settings Encryption Class
 *
 * @package     ArrayPress\WP\Encryption
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EncryptionUtils;

use ArrayPress\EncryptionUtils\Traits\{
	Build,
	Core,
	Constants,
	Env,
	Options,
	AutoIntercept,
	Transients,
	UserMeta,
	PostMeta
};

defined( 'ABSPATH' ) || exit;

/**
 * Class Manager
 *
 * Automatically checks for WordPress constants before falling back to encrypted database storage.
 * Features auto-interception of get_option() calls to return decrypted values seamlessly.
 */
class Manager {
	use Build;
	use Core;
	use Constants;
	use Env;
	use Options;
	use AutoIntercept;
	use Transients;
	use UserMeta;
	use PostMeta;

	/**
	 * Constructor - Clean and simple!
	 *
	 * @param string      $prefix         Prefix for options/constants (e.g., 'wc_r2')
	 * @param string|null $key            Optional. Custom encryption key. If null, use WordPress salts.
	 * @param bool        $auto_intercept Optional. Whether to automatically intercept get_option calls. Default true.
	 */
	public function __construct( string $prefix, ?string $key = null, bool $auto_intercept = true ) {
		$this->key            = $key ? hash( 'sha256', $key, true ) : $this->get_wordpress_key();
		$this->prefix_name    = $this->build_option_prefix( $prefix );
		$this->prefix         = $this->build_encryption_prefix( $prefix );
		$this->auto_intercept = $auto_intercept;

		$this->validate_environment();

		if ( $this->auto_intercept ) {
			$this->setup_auto_interception();
		}
	}

}
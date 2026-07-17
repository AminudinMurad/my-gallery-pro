<?php
/**
 * First-party class autoloader.
 *
 * @package MyGalleryPro
 */

namespace MyGalleryPro;

defined( 'ABSPATH' ) || exit;

/**
 * Loads MY Gallery PRO classes from the src directory.
 */
final class Autoloader {

	/**
	 * Namespace prefix handled by this autoloader.
	 */
	private const PREFIX = 'MyGalleryPro\\';

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a class from the plugin source tree.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function load( string $class ): void {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( self::PREFIX ) );

		if ( false === $relative_class || '' === $relative_class ) {
			return;
		}

		if ( 1 !== preg_match( '/\A[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/D', $relative_class ) ) {
			return;
		}

		$file = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}

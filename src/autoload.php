<?php
/**
 * PSR-4 autoloader for the plugin's classes.
 *
 * Lets the plugin run without Composer in production; Composer is only
 * needed for development tools.
 *
 * @package AdilElsaeed\LDRegrader
 */

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'AdilElsaeed\\LDRegrader\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

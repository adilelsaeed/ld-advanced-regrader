<?php
/**
 * PHPUnit bootstrap: unit tests run without WordPress, using Brain Monkey.
 *
 * @package AdilElsaeed\LDRegrader
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once dirname( __DIR__, 2 ) . '/src/autoload.php';
require_once __DIR__ . '/stubs/wp-classes.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

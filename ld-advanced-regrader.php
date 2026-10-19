<?php
/**
 * Plugin Name:       Advanced Regrader for LearnDash
 * Plugin URI:        https://github.com/adilelsaeed/ld-advanced-regrader
 * Description:       Recalculate LearnDash quiz scores for past attempts after questions, answers or points change, with a preview before saving.
 * Version:           2.0.0
 * Author:            Adil Elsaeed
 * Author URI:        https://adilelsaeed.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ld-advanced-regrader
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      7.4
 *
 * @package AdilElsaeed\LDRegrader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LDAR_VERSION', '2.0.0' );
define( 'LDAR_PLUGIN_FILE', __FILE__ );
define( 'LDAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LDAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LDAR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once LDAR_PLUGIN_DIR . 'src/autoload.php';

add_action(
	'plugins_loaded',
	static function () {
		( new AdilElsaeed\LDRegrader\Plugin() )->boot();
	},
	20 // After LearnDash has defined LEARNDASH_VERSION.
);

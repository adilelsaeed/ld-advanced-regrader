<?php
/**
 * Uninstall handler for Advanced Regrader for LearnDash.
 *
 * Fired when the plugin is uninstalled via the WordPress admin.
 *
 * @package LD_Advanced_Regrader
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// This plugin does not create custom database tables or options.
// No cleanup is required at this time.

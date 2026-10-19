<?php
/**
 * Admin page.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Admin;

use AdilElsaeed\LDRegrader\Plugin;

/**
 * Registers the LearnDash submenu page and mounts the React app on it.
 *
 * @since 2.0.0
 */
class AdminPage {

	const SLUG = 'ld-advanced-regrader';

	const HANDLE = 'ld-advanced-regrader-admin';

	/**
	 * Hook suffix returned by add_submenu_page().
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the submenu page under LearnDash LMS.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$hook_suffix = add_submenu_page(
			'learndash-lms',
			__( 'Regrade Scores', 'ld-advanced-regrader' ),
			__( 'Regrade Scores', 'ld-advanced-regrader' ),
			Plugin::capability(),
			self::SLUG,
			array( $this, 'render' )
		);

		$this->hook_suffix = is_string( $hook_suffix ) ? $hook_suffix : '';
	}

	/**
	 * Enqueue the built app on this page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue( $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $this->hook_suffix !== $hook_suffix ) {
			return;
		}

		$asset_file = LDAR_PLUGIN_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			self::HANDLE,
			LDAR_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.ldarSettings = ' . wp_json_encode(
				array(
					'batchSize'   => Plugin::batch_size(),
					'perPage'     => 20,
					'learnDashOn' => Plugin::learndash_active(),
				)
			) . ';',
			'before'
		);

		wp_set_script_translations( self::HANDLE, 'ld-advanced-regrader', LDAR_PLUGIN_DIR . 'languages' );

		if ( file_exists( LDAR_PLUGIN_DIR . 'build/style-index.css' ) ) {
			wp_enqueue_style( self::HANDLE, LDAR_PLUGIN_URL . 'build/style-index.css', array( 'wp-components' ), $asset['version'] );
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}
	}

	/**
	 * Render the mount point.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Regrade Quiz Scores', 'ld-advanced-regrader' ) . '</h1>';

		if ( ! file_exists( LDAR_PLUGIN_DIR . 'build/index.js' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The admin app has not been built. Run "npm install && npm run build" in the plugin folder.', 'ld-advanced-regrader' ) . '</p></div></div>';
			return;
		}

		echo '<div id="ldar-app"></div></div>';
	}
}

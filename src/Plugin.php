<?php
/**
 * Plugin bootstrap.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader;

use AdilElsaeed\LDRegrader\Admin\AdminPage;
use AdilElsaeed\LDRegrader\Data\AttemptRepository;
use AdilElsaeed\LDRegrader\Data\QuizRepository;
use AdilElsaeed\LDRegrader\Regrade\Regrader;
use AdilElsaeed\LDRegrader\Regrade\UserQuizMetaUpdater;
use AdilElsaeed\LDRegrader\Rest\RegradeController;
use AdilElsaeed\LDRegrader\Scoring\ScoreCalculator;

/**
 * Wires the plugin's services together and hooks them into WordPress.
 *
 * @since 2.0.0
 */
class Plugin {

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public function boot(): void {
		load_plugin_textdomain( 'ld-advanced-regrader', false, dirname( LDAR_PLUGIN_BASENAME ) . '/languages' );

		if ( ! self::learndash_active() ) {
			add_action( 'admin_notices', array( $this, 'missing_learndash_notice' ) );
			return;
		}

		$quizzes  = new QuizRepository();
		$regrader = new Regrader( new AttemptRepository(), $quizzes, new ScoreCalculator(), new UserQuizMetaUpdater() );
		$rest     = new RegradeController( $regrader, $quizzes );

		add_action( 'rest_api_init', array( $rest, 'register_routes' ) );
		( new AdminPage() )->register();
	}

	/**
	 * Capability required to use the regrader.
	 *
	 * @return string
	 */
	public static function capability(): string {
		/**
		 * Filters the capability required to preview and regrade attempts.
		 *
		 * @since 2.0.0
		 *
		 * @param string $capability Default 'manage_options'.
		 */
		return (string) apply_filters( 'ldar_capability', 'manage_options' );
	}

	/**
	 * Attempts processed per batch request.
	 *
	 * @return int
	 */
	public static function batch_size(): int {
		/**
		 * Filters how many attempts one batch request regrades.
		 *
		 * Lower it on slow hosts that hit PHP time limits.
		 *
		 * @since 2.0.0
		 *
		 * @param int $size Default 50.
		 */
		return max( 1, min( 500, (int) apply_filters( 'ldar_batch_size', 50 ) ) );
	}

	/**
	 * Whether LearnDash is active.
	 *
	 * @return bool
	 */
	public static function learndash_active(): bool {
		return defined( 'LEARNDASH_VERSION' );
	}

	/**
	 * Admin notice shown when LearnDash is missing.
	 *
	 * @return void
	 */
	public function missing_learndash_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Advanced Regrader for LearnDash requires LearnDash LMS to be installed and active.', 'ld-advanced-regrader' ) . '</p></div>';
	}
}

<?php
/**
 * Plugin Name: Advanced Regrader for LearnDash
 * Plugin URI:  https://adilelsaeed.com/ld-advanced-regrader
 * Description: Advanced interface for recalculating quiz grades with a preview before saving.
 * Version:     1.0.2
 * Author:      Adil Elsaeed
 * Author URI:  https://adilelsaeed.com/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ld-advanced-regrader
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package LD_Advanced_Regrader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'LDAR_VERSION', '1.0.2' );
define( 'LDAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LDAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LDAR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
class LD_Advanced_Regrader {

	/**
	 * The admin page hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'wp_ajax_ld_regrade_batch_process', array( $this, 'ajax_batch_process' ) );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'ld-advanced-regrader', false, dirname( LDAR_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Register admin submenu under LearnDash.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		$this->hook_suffix = add_submenu_page(
			'learndash-lms',
			esc_html__( 'Regrade Scores', 'ld-advanced-regrader' ),
			esc_html__( 'Regrade Scores', 'ld-advanced-regrader' ),
			'manage_options',
			'ld-advanced-regrader',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS only on the plugin page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( $this->hook_suffix !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'ld-advanced-regrader-admin',
			LDAR_PLUGIN_URL . 'admin/css/admin-styles.css',
			array(),
			LDAR_VERSION
		);

		wp_enqueue_script(
			'ld-advanced-regrader-admin',
			LDAR_PLUGIN_URL . 'admin/js/admin-scripts.js',
			array( 'jquery' ),
			LDAR_VERSION,
			true
		);
	}

	/**
	 * Pass dynamic data to the JS via wp_localize_script.
	 *
	 * Called from render_preview_table() so data is only available when needed.
	 *
	 * @since 1.0.2
	 *
	 * @param int    $total_items  Total number of filtered attempts.
	 * @param int    $quiz_id      The quiz post ID.
	 * @param int    $group_id     The group post ID (0 for all).
	 * @param string $search_query The user search string.
	 */
	private function localize_script_data( $total_items, $quiz_id, $group_id, $search_query ) {
		wp_localize_script( 'ld-advanced-regrader-admin', 'ldAdvancedRegrader', array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'ld_regrade_batch_nonce' ),
			'totalItems'  => $total_items,
			'quizId'      => $quiz_id,
			'groupId'     => $group_id,
			'searchQuery' => $search_query,
			'i18n'        => array(
				'confirmBatch' => __( 'Are you sure you want to recalculate and save scores for ALL matching attempts? This may take some time.', 'ld-advanced-regrader' ),
				'completed'    => __( 'Completed!', 'ld-advanced-regrader' ),
				'errorBatch'   => __( 'An error occurred during batch processing.', 'ld-advanced-regrader' ),
				'serverError'  => __( 'Server error during batch processing. Please check your server logs.', 'ld-advanced-regrader' ),
			),
		) );
	}

	/**
	 * Render the main admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Process the actual update request (when the save button is clicked).
		if ( isset( $_POST['confirm_regrade'] ) && check_admin_referer( 'ld_regrade_save_action', 'ld_regrade_save_nonce' ) ) {
			$users_to_update = isset( $_POST['users_to_update'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['users_to_update'] ) ) : array();
			$post_quiz_id    = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
			$this->save_new_scores( $users_to_update, $post_quiz_id );
		}

		$selected_quiz_id  = isset( $_REQUEST['quiz_id'] ) ? intval( $_REQUEST['quiz_id'] ) : 0;
		$selected_group_id = isset( $_REQUEST['group_id'] ) ? intval( $_REQUEST['group_id'] ) : 0;
		$search_query      = isset( $_REQUEST['user_search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['user_search'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Advanced LearnDash Regrader Tool', 'ld-advanced-regrader' ); ?></h1>
			<p><?php esc_html_e( 'Select a quiz to view students and simulate new grades based on current question modifications.', 'ld-advanced-regrader' ); ?></p>

			<div class="search-box-container">
				<form method="get" action="">
					<input type="hidden" name="page" value="ld-advanced-regrader">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="quiz_id"><?php esc_html_e( 'Select Quiz', 'ld-advanced-regrader' ); ?></label></th>
							<td>
								<select name="quiz_id" id="quiz_id">
									<option value="0"><?php esc_html_e( '-- Select Quiz --', 'ld-advanced-regrader' ); ?></option>
									<?php
									$quizzes = get_posts( array(
										'post_type'      => 'sfwd-quiz',
										'posts_per_page' => -1,
										'orderby'        => 'title',
										'order'          => 'ASC',
									) );
									foreach ( $quizzes as $quiz ) {
										printf(
											'<option value="%d" %s>%s</option>',
											intval( $quiz->ID ),
											selected( $selected_quiz_id, $quiz->ID, false ),
											esc_html( $quiz->post_title )
										);
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="group_id"><?php esc_html_e( 'Select Group', 'ld-advanced-regrader' ); ?></label></th>
							<td>
								<select name="group_id" id="group_id">
									<option value="0"><?php esc_html_e( '-- All Groups --', 'ld-advanced-regrader' ); ?></option>
									<?php
									$groups = get_posts( array(
										'post_type'      => 'groups',
										'posts_per_page' => -1,
										'orderby'        => 'title',
										'order'          => 'ASC',
									) );
									foreach ( $groups as $group ) {
										printf(
											'<option value="%d" %s>%s</option>',
											intval( $group->ID ),
											selected( $selected_group_id, $group->ID, false ),
											esc_html( $group->post_title )
										);
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="user_search"><?php esc_html_e( 'Search User (Name or Email)', 'ld-advanced-regrader' ); ?></label></th>
							<td>
								<input type="text" name="user_search" id="user_search" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'e.g. John, email@example.com', 'ld-advanced-regrader' ); ?>">
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Show Results & Simulation', 'ld-advanced-regrader' ); ?></button>
							</td>
						</tr>
					</table>
				</form>
			</div>

			<?php
			if ( $selected_quiz_id > 0 ) {
				$this->render_preview_table( $selected_quiz_id, $search_query, $selected_group_id );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Display preview table (Current score vs Expected score).
	 *
	 * @since 1.0.0
	 *
	 * @param int    $quiz_id      The quiz post ID.
	 * @param string $search_query User search string.
	 * @param int    $group_id     Group post ID (0 for all).
	 */
	private function render_preview_table( $quiz_id, $search_query = '', $group_id = 0 ) {
		global $wpdb;

		$activity_table = $wpdb->prefix . 'learndash_user_activity';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$attempts = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$activity_table} WHERE post_id = %d AND activity_type = 'quiz' ORDER BY activity_completed DESC",
			$quiz_id
		) );

		if ( empty( $attempts ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'No students found for this quiz.', 'ld-advanced-regrader' ) . '</p></div>';
			return;
		}

		// Apply search and group filters.
		$filtered_attempts = array();
		foreach ( $attempts as $attempt ) {
			$user = get_userdata( $attempt->user_id );
			if ( ! $user ) {
				continue;
			}

			// Filter by Group.
			if ( $group_id > 0 && function_exists( 'learndash_is_user_in_group' ) ) {
				if ( ! learndash_is_user_in_group( $user->ID, $group_id ) ) {
					continue;
				}
			}

			if ( ! empty( $search_query ) ) {
				if ( false === stripos( $user->display_name, $search_query )
					&& false === stripos( $user->user_email, $search_query )
					&& false === stripos( $user->user_login, $search_query ) ) {
					continue;
				}
			}
			$filtered_attempts[] = $attempt;
		}

		if ( empty( $filtered_attempts ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'No results match your search.', 'ld-advanced-regrader' ) . '</p></div>';
			return;
		}

		$total_items  = count( $filtered_attempts );
		$per_page     = 50;
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

		$page_attempts = array_slice( $filtered_attempts, ( $current_page - 1 ) * $per_page, $per_page );

		// Localize script data for the batch JS.
		$this->localize_script_data( $total_items, $quiz_id, $group_id, $search_query );

		echo '<div class="notice notice-info"><p>';
		printf(
			/* translators: 1: start number, 2: end number, 3: total number */
			esc_html__( 'Showing %1$d to %2$d of %3$d attempts.', 'ld-advanced-regrader' ),
			( $current_page - 1 ) * $per_page + 1,
			min( $current_page * $per_page, $total_items ),
			$total_items
		);
		echo '</p></div>';

		echo '<form method="post" action="">';
		wp_nonce_field( 'ld_regrade_save_action', 'ld_regrade_save_nonce' );
		echo '<input type="hidden" name="quiz_id" value="' . intval( $quiz_id ) . '">';
		echo '<input type="hidden" name="confirm_regrade" value="1">';

		echo '<table class="ld-regrade-table">';
		echo '<thead>
				<tr>
					<th><input type="checkbox" id="select-all"></th>
					<th>' . esc_html__( 'Student', 'ld-advanced-regrader' ) . '</th>
					<th>' . esc_html__( 'Date', 'ld-advanced-regrader' ) . '</th>
					<th>' . esc_html__( 'Registered Score', 'ld-advanced-regrader' ) . '</th>
					<th>' . esc_html__( 'Registered %', 'ld-advanced-regrader' ) . '</th>
					<th>' . esc_html__( 'New Score (Simulated)', 'ld-advanced-regrader' ) . '</th>
					<th>' . esc_html__( 'New %', 'ld-advanced-regrader' ) . '</th>
					<th>' . esc_html__( 'Difference', 'ld-advanced-regrader' ) . '</th>
				</tr>
			  </thead><tbody>';

		foreach ( $page_attempts as $activity ) {
			$user_id = $activity->user_id;
			$user    = get_userdata( $user_id );

			$activity_meta = $this->get_activity_meta( $activity->activity_id );

			$current_score    = isset( $activity_meta['score'] ) ? intval( $activity_meta['score'] ) : 0;
			$statistic_ref_id = isset( $activity_meta['statistic_ref_id'] ) ? intval( $activity_meta['statistic_ref_id'] ) : 0;

			// Calculate the actual score (Re-calculation Logic).
			$calculated_data = $this->calculate_actual_score( $activity, $quiz_id, $statistic_ref_id );
			$new_score       = $calculated_data['score'];

			// Determine the difference.
			$diff       = $new_score - $current_score;
			$diff_class = 'score-diff-neutral';
			$diff_text  = '0';

			if ( $diff > 0 ) {
				$diff_class = 'score-diff-positive';
				$diff_text  = '+' . $diff;
			} elseif ( $diff < 0 ) {
				$diff_class = 'score-diff-negative';
				$diff_text  = (string) $diff;
			}

			// Include new score data in a hidden field to pass it during save.
			$input_data = base64_encode( wp_json_encode( array(
				'activity_id'      => $activity->activity_id,
				'new_score'        => $new_score,
				'total_points'     => $calculated_data['total_points'],
				'question_results' => $calculated_data['question_results'],
				'statistic_ref_id' => $statistic_ref_id,
			) ) );

			$new_total_points = intval( $calculated_data['total_points'] );
			$new_percentage   = ( $new_total_points > 0 ) ? round( ( $new_score / $new_total_points ) * 100, 2 ) : 0;

			$current_percentage = isset( $activity_meta['percentage'] ) ? floatval( $activity_meta['percentage'] ) : 0;
			// Fallback calculation if percentage is missing but we have points.
			if ( 0 === (int) $current_percentage && isset( $activity_meta['points'] ) && intval( $activity_meta['points'] ) > 0 ) {
				$current_percentage = round( ( $current_score / intval( $activity_meta['points'] ) ) * 100, 2 );
			}

			$display_name = $user ? esc_html( $user->display_name ) : esc_html__( 'Deleted User', 'ld-advanced-regrader' );
			$date_display = esc_html( gmdate( 'Y-m-d H:i', $activity->activity_completed ) );

			echo '<tr>';
			echo '<td><input type="checkbox" name="users_to_update[]" value="' . esc_attr( $input_data ) . '"></td>';
			echo '<td>' . $display_name . ' (' . intval( $user_id ) . ')</td>';
			echo '<td>' . $date_display . '</td>';
			echo '<td>' . intval( $current_score ) . '</td>';
			echo '<td>' . esc_html( $current_percentage ) . '%</td>';
			echo '<td><strong>' . intval( $new_score ) . '</strong></td>';
			echo '<td><strong>' . esc_html( $new_percentage ) . '%</strong></td>';
			echo '<td class="' . esc_attr( $diff_class ) . '">' . esc_html( $diff_text ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$pagination_args = array(
			'base'      => add_query_arg( 'paged', '%#%' ),
			'format'    => '',
			'prev_text' => __( '&laquo; Previous', 'ld-advanced-regrader' ),
			'next_text' => __( 'Next &raquo;', 'ld-advanced-regrader' ),
			'total'     => ceil( $total_items / $per_page ),
			'current'   => $current_page,
		);
		$pagination = paginate_links( $pagination_args );
		if ( $pagination ) {
			echo '<div class="ld-regrade-pagination">' . wp_kses_post( $pagination ) . '</div>';
		}

		echo '<p style="margin-top:15px;">
				<label style="margin-left: 15px;">
					<input type="checkbox" name="update_course_progress" id="update_course_progress" value="1">
					' . esc_html__( 'Update Course Completion (if student passes)', 'ld-advanced-regrader' ) . '
				</label>
				<button type="submit" id="btn-update-selected" class="button button-primary button-large">' . esc_html__( 'Update Selected Students Grades', 'ld-advanced-regrader' ) . '</button>
				<button type="button" id="btn-regrade-all" class="button button-secondary button-large" style="margin-left:10px;">' . esc_html__( 'Auto Regrade All Matching (Batch)', 'ld-advanced-regrader' ) . '</button>
			  </p>';

		echo '<div id="batch-progress-container" style="display:none;">
				<h4>' . esc_html__( 'Batch Regrading Progress', 'ld-advanced-regrader' ) . '</h4>
				<progress id="batch-progress-bar" value="0" max="' . intval( $total_items ) . '"></progress>
				<p id="batch-progress-text">0 / ' . intval( $total_items ) . '</p>
			  </div>';

		echo '</form>';
	}

	/**
	 * Core function: Calculate the actual score based on the current question settings.
	 *
	 * @since 1.0.0
	 *
	 * @param object $activity         The activity row object.
	 * @param int    $quiz_id          The quiz post ID.
	 * @param int    $statistic_ref_id The statistic reference ID.
	 * @return array Score data with 'score', 'total_points', 'question_results'.
	 */
	private function calculate_actual_score( $activity, $quiz_id, $statistic_ref_id = 0 ) {
		global $wpdb;

		// 1. Determine the ProQuiz Master ID associated with the LearnDash Quiz.
		$pro_quiz_id = get_post_meta( $quiz_id, 'quiz_pro_id', true );
		if ( ! $pro_quiz_id ) {
			return array(
				'score'            => 0,
				'total_points'     => 0,
				'question_results' => array(),
			);
		}

		// 2. Fetch all current questions for this quiz.
		$question_mapper = new WpProQuiz_Model_QuestionMapper();
		$questions       = $question_mapper->fetchAll( $pro_quiz_id );

		// 3. Fetch user answers from statistics tables.
		$stat_table = $wpdb->prefix . 'learndash_pro_quiz_statistic';
		$ref_table  = $wpdb->prefix . 'learndash_pro_quiz_statistic_ref';

		if ( $statistic_ref_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_stats = $wpdb->get_results( $wpdb->prepare(
				"SELECT question_id, answer_data FROM {$stat_table} WHERE statistic_ref_id = %d",
				$statistic_ref_id
			) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_stats = $wpdb->get_results( $wpdb->prepare(
				"SELECT question_id, answer_data FROM {$stat_table}
				WHERE statistic_ref_id = (
					SELECT statistic_ref_id FROM {$ref_table}
					WHERE user_id = %d AND quiz_id = %d AND ABS(create_time - %d) <= 60
					ORDER BY create_time DESC LIMIT 1
				)",
				$activity->user_id,
				$pro_quiz_id,
				$activity->activity_completed
			) );
		}

		// Map user answers for easier search by question ID.
		$user_answers_map = array();
		foreach ( $user_stats as $stat ) {
			$data = maybe_unserialize( $stat->answer_data );
			if ( is_string( $data ) ) {
				$decoded = json_decode( $data, true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					$data = $decoded;
				}
			}
			$user_answers_map[ $stat->question_id ] = $data;
		}

		$calculated_score      = 0;
		$total_possible_points = 0;
		$question_results      = array();

		// 4. Compare user answers with current question settings.
		foreach ( $questions as $question ) {
			$q_id   = $question->getId();
			$points = $question->getPoints();

			$total_possible_points += $points;

			if ( isset( $user_answers_map[ $q_id ] ) ) {
				$user_answer_data = $user_answers_map[ $q_id ];
				$type             = $question->getAnswerType();

				$is_correct = false;

				switch ( $type ) {
					case 'single':
						$is_correct = $this->is_correct_single( $question, $user_answer_data );
						break;
					case 'multiple':
						$is_correct = $this->is_correct_multiple( $question, $user_answer_data );
						break;
					default:
						$is_correct = false;
						break;
				}

				if ( $is_correct ) {
					$calculated_score += $points;
				}

				$question_results[ $q_id ] = array(
					'correct' => $is_correct,
					'points'  => $is_correct ? $points : 0,
				);
			}
		}

		return array(
			'score'            => $calculated_score,
			'total_points'     => $total_possible_points,
			'question_results' => $question_results,
		);
	}

	/**
	 * Validate "Single Choice" answer correctness.
	 *
	 * @since 1.0.0
	 *
	 * @param object $question        The question model object.
	 * @param mixed  $user_answer_data The user's submitted answer data.
	 * @return bool True if answer is correct.
	 */
	private function is_correct_single( $question, $user_answer_data ) {
		$answer_data = $question->getAnswerData();

		foreach ( $answer_data as $index => $answer_obj ) {
			$should_be_selected = $answer_obj->isCorrect();
			$is_selected        = false;

			if ( is_array( $user_answer_data ) ) {
				if ( isset( $user_answer_data[ $index ] ) && 1 == $user_answer_data[ $index ] ) {
					$is_selected = true;
				}
			} elseif ( $user_answer_data == $index ) {
				$is_selected = true;
			}

			if ( $should_be_selected !== $is_selected ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validate "Multiple Choice" answer correctness.
	 *
	 * @since 1.0.0
	 *
	 * @param object $question        The question model object.
	 * @param mixed  $user_answer_data The user's submitted answer data.
	 * @return bool True if answer is correct.
	 */
	private function is_correct_multiple( $question, $user_answer_data ) {
		$answer_data = $question->getAnswerData();

		foreach ( $answer_data as $index => $answer_obj ) {
			$should_be_selected = $answer_obj->isCorrect();
			$is_selected        = false;

			if ( is_array( $user_answer_data ) ) {
				if ( isset( $user_answer_data[ $index ] ) && 1 == $user_answer_data[ $index ] ) {
					$is_selected = true;
				}
			}

			if ( $should_be_selected !== $is_selected ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Save new scores for selected students.
	 *
	 * @since 1.0.0
	 *
	 * @param array $encoded_data_array Array of base64-encoded JSON score data.
	 * @param int   $quiz_id           The quiz post ID.
	 */
	private function save_new_scores( $encoded_data_array, $quiz_id ) {
		if ( empty( $encoded_data_array ) ) {
			return;
		}

		$count                  = 0;
		$update_course_progress = isset( $_POST['update_course_progress'] );

		$quiz_meta            = get_post_meta( $quiz_id, '_sfwd-quiz', true );
		$passing_percentage   = isset( $quiz_meta['sfwd-quiz_passingpercentage'] ) ? floatval( $quiz_meta['sfwd-quiz_passingpercentage'] ) : 0;
		$pro_quiz_id          = get_post_meta( $quiz_id, 'quiz_pro_id', true );

		foreach ( $encoded_data_array as $encoded_item ) {
			$data = json_decode( base64_decode( $encoded_item ), true );
			if ( $this->update_single_attempt_score( $data, $quiz_id, $pro_quiz_id, $passing_percentage, $update_course_progress ) ) {
				$count++;
			}
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			/* translators: %d: number of updated students */
			sprintf( esc_html__( 'Successfully updated scores for %d students.', 'ld-advanced-regrader' ), $count )
		);
	}

	/**
	 * Helper: Reusable update logic suitable for AJAX or manual processing.
	 *
	 * @since 1.0.2
	 *
	 * @param array  $data                   Decoded score data for one attempt.
	 * @param int    $quiz_id                The quiz post ID.
	 * @param int    $pro_quiz_id            The ProQuiz master ID.
	 * @param float  $passing_percentage     The passing percentage threshold.
	 * @param bool   $update_course_progress Whether to update course progress.
	 * @return bool True on success.
	 */
	private function update_single_attempt_score( $data, $quiz_id, $pro_quiz_id, $passing_percentage, $update_course_progress ) {
		global $wpdb;

		$activity_table = $wpdb->prefix . 'learndash_user_activity';

		$activity_id = isset( $data['activity_id'] ) ? intval( $data['activity_id'] ) : 0;
		if ( ! $activity_id ) {
			return false;
		}

		$new_score        = intval( $data['new_score'] );
		$total_points     = intval( $data['total_points'] );
		$statistic_ref_id = isset( $data['statistic_ref_id'] ) ? intval( $data['statistic_ref_id'] ) : 0;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$activity = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$activity_table} WHERE activity_id = %d",
			$activity_id
		) );

		if ( ! $activity ) {
			return false;
		}

		$user_id   = $activity->user_id;
		$course_id = $activity->course_id;

		$activity_meta              = $this->get_activity_meta( $activity_id );
		$old_score                  = isset( $activity_meta['score'] ) ? intval( $activity_meta['score'] ) : 0;
		$activity_meta['regraded']       = 1;
		$activity_meta['regraded_date']  = current_time( 'mysql' );
		$activity_meta['original_score'] = $old_score;

		$new_percentage = 0;
		if ( $total_points > 0 ) {
			$new_percentage = round( ( $new_score / $total_points ) * 100, 2 );
		}

		$new_pass = ( $new_percentage >= $passing_percentage ) ? 1 : 0;

		$activity_meta['score']        = $new_score;
		$activity_meta['points']       = $new_score;
		$activity_meta['total_points'] = $total_points;
		$activity_meta['percentage']   = $new_percentage;
		$activity_meta['pass']         = $new_pass;

		$this->update_activity_meta( $activity_id, $activity_meta );

		$wpdb->update(
			$activity_table,
			array( 'activity_status' => $new_pass ),
			array( 'activity_id' => $activity_id ),
			array( '%d' ),
			array( '%d' )
		);

		$this->update_user_quiz_meta( $user_id, $quiz_id, $pro_quiz_id, $activity, $new_score, $total_points, $new_percentage, $new_pass, $statistic_ref_id );

		$question_results = isset( $data['question_results'] ) ? $data['question_results'] : array();
		if ( ! empty( $question_results ) && $pro_quiz_id ) {
			$this->update_pro_quiz_statistics( $user_id, $pro_quiz_id, $question_results, $statistic_ref_id, $activity->activity_completed );
		}

		if ( $update_course_progress && $activity ) {
			if ( $new_pass && function_exists( 'learndash_process_mark_complete' ) ) {
				learndash_process_mark_complete( $user_id, $quiz_id, false, $course_id );
			}
			if ( ! empty( $course_id ) && function_exists( 'learndash_process_mark_complete' ) ) {
				learndash_process_mark_complete( $user_id, $course_id, false, $course_id );
			}
		}

		return true;
	}

	/**
	 * Update the _sfwd-quizzes user meta to reflect new scores.
	 *
	 * @since 1.0.1
	 *
	 * @param int    $user_id          The user ID.
	 * @param int    $quiz_id          The quiz post ID.
	 * @param int    $pro_quiz_id      The ProQuiz master ID.
	 * @param object $activity         The activity row object.
	 * @param int    $new_score        The new calculated score.
	 * @param int    $total_points     The total possible points.
	 * @param float  $new_percentage   The new percentage.
	 * @param int    $new_pass         1 = pass, 0 = fail.
	 * @param int    $statistic_ref_id The statistic reference ID.
	 */
	private function update_user_quiz_meta( $user_id, $quiz_id, $pro_quiz_id, $activity, $new_score, $total_points, $new_percentage, $new_pass, $statistic_ref_id = 0 ) {
		$user_quiz_meta = get_user_meta( $user_id, '_sfwd-quizzes', true );
		if ( ! is_array( $user_quiz_meta ) ) {
			return;
		}

		$updated = false;

		foreach ( $user_quiz_meta as $key => $quiz_attempt ) {
			if ( ! isset( $quiz_attempt['quiz'] ) ) {
				continue;
			}

			if ( intval( $quiz_attempt['quiz'] ) !== intval( $quiz_id ) ) {
				continue;
			}

			if ( isset( $quiz_attempt['time'] ) && $activity->activity_completed > 0 ) {
				if ( abs( intval( $quiz_attempt['time'] ) - intval( $activity->activity_completed ) ) > 60 ) {
					continue;
				}
			}

			$matched = false;
			if ( $statistic_ref_id > 0 && isset( $quiz_attempt['statistic_ref_id'] ) && intval( $quiz_attempt['statistic_ref_id'] ) === $statistic_ref_id ) {
				$matched = true;
			} elseif ( isset( $quiz_attempt['time'] ) && $activity->activity_completed > 0 ) {
				if ( abs( intval( $quiz_attempt['time'] ) - intval( $activity->activity_completed ) ) <= 60 ) {
					$matched = true;
				}
			}

			if ( ! $matched ) {
				continue;
			}

			$user_quiz_meta[ $key ]['score']        = $new_score;
			$user_quiz_meta[ $key ]['points']       = $new_score;
			$user_quiz_meta[ $key ]['total_points'] = $total_points;
			$user_quiz_meta[ $key ]['percentage']   = $new_percentage;
			$user_quiz_meta[ $key ]['pass']         = $new_pass;
			$user_quiz_meta[ $key ]['m_edit_by']    = get_current_user_id();
			$user_quiz_meta[ $key ]['m_edit_time']  = time();
			$updated = true;
			break;
		}

		// Fallback: update the last attempt for this quiz.
		if ( ! $updated ) {
			for ( $i = count( $user_quiz_meta ) - 1; $i >= 0; $i-- ) {
				if ( isset( $user_quiz_meta[ $i ]['quiz'] ) && intval( $user_quiz_meta[ $i ]['quiz'] ) === intval( $quiz_id ) ) {
					$user_quiz_meta[ $i ]['score']        = $new_score;
					$user_quiz_meta[ $i ]['points']       = $new_score;
					$user_quiz_meta[ $i ]['total_points'] = $total_points;
					$user_quiz_meta[ $i ]['percentage']   = $new_percentage;
					$user_quiz_meta[ $i ]['pass']         = $new_pass;
					$user_quiz_meta[ $i ]['m_edit_by']    = get_current_user_id();
					$user_quiz_meta[ $i ]['m_edit_time']  = time();
					$updated = true;
					break;
				}
			}
		}

		if ( $updated ) {
			update_user_meta( $user_id, '_sfwd-quizzes', $user_quiz_meta );
		}
	}

	/**
	 * Update the pro quiz statistic table per-question.
	 *
	 * @since 1.0.1
	 *
	 * @param int   $user_id            The user ID.
	 * @param int   $pro_quiz_id        The ProQuiz master ID.
	 * @param array $question_results   Array of per-question results.
	 * @param int   $statistic_ref_id   The statistic reference ID.
	 * @param int   $activity_completed Unix timestamp of activity completion.
	 */
	private function update_pro_quiz_statistics( $user_id, $pro_quiz_id, $question_results, $statistic_ref_id = 0, $activity_completed = 0 ) {
		global $wpdb;

		$stat_table = $wpdb->prefix . 'learndash_pro_quiz_statistic';
		$ref_table  = $wpdb->prefix . 'learndash_pro_quiz_statistic_ref';

		$ref_id = $statistic_ref_id;

		if ( ! $ref_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ref_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT statistic_ref_id FROM {$ref_table}
				WHERE user_id = %d AND quiz_id = %d AND ABS(create_time - %d) <= 60
				ORDER BY create_time DESC LIMIT 1",
				$user_id,
				$pro_quiz_id,
				$activity_completed
			) );
		}

		if ( ! $ref_id ) {
			return;
		}

		foreach ( $question_results as $question_id => $result ) {
			$question_id = intval( $question_id );
			$is_correct  = $result['correct'];
			$points      = floatval( $result['points'] );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$stat_table} WHERE statistic_ref_id = %d AND question_id = %d",
				$ref_id,
				$question_id
			) );

			if ( $exists ) {
				$wpdb->update(
					$stat_table,
					array(
						'correct_count'   => $is_correct ? 1 : 0,
						'incorrect_count' => $is_correct ? 0 : 1,
						'points'          => $points,
					),
					array(
						'statistic_ref_id' => $ref_id,
						'question_id'      => $question_id,
					),
					array( '%d', '%d', '%f' ),
					array( '%d', '%d' )
				);
			}
		}
	}

	/**
	 * Fetch meta from learndash_user_activity_meta table.
	 *
	 * @since 1.0.0
	 *
	 * @param int $activity_id The activity ID.
	 * @return array Associative array of meta key => value.
	 */
	private function get_activity_meta( $activity_id ) {
		global $wpdb;

		$meta_table = $wpdb->prefix . 'learndash_user_activity_meta';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT activity_meta_key, activity_meta_value FROM {$meta_table} WHERE activity_id = %d",
			$activity_id
		) );

		$meta = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$meta[ $row->activity_meta_key ] = maybe_unserialize( $row->activity_meta_value );
			}
		}
		return $meta;
	}

	/**
	 * Update meta in learndash_user_activity_meta table.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $activity_id The activity ID.
	 * @param array $meta_data   Associative array of meta key => value.
	 */
	private function update_activity_meta( $activity_id, $meta_data ) {
		global $wpdb;

		$meta_table = $wpdb->prefix . 'learndash_user_activity_meta';

		foreach ( $meta_data as $key => $value ) {
			$serialized_data = maybe_serialize( $value );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT activity_meta_id FROM {$meta_table} WHERE activity_id = %d AND activity_meta_key = %s",
				$activity_id,
				$key
			) );

			if ( $exists ) {
				$wpdb->update(
					$meta_table,
					array( 'activity_meta_value' => $serialized_data ),
					array(
						'activity_id'       => $activity_id,
						'activity_meta_key' => $key,
					)
				);
			} else {
				$wpdb->insert(
					$meta_table,
					array(
						'activity_id'          => $activity_id,
						'activity_meta_key'    => $key,
						'activity_meta_value'  => $serialized_data,
					)
				);
			}
		}
	}

	/**
	 * AJAX Handler for batch processing attempts.
	 *
	 * @since 1.0.2
	 */
	public function ajax_batch_process() {
		check_ajax_referer( 'ld_regrade_batch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ld-advanced-regrader' ) );
		}

		$quiz_id      = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
		$group_id     = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
		$search_query = isset( $_POST['search_query'] ) ? sanitize_text_field( wp_unslash( $_POST['search_query'] ) ) : '';
		$offset       = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$limit        = 50;
		$update_course = isset( $_POST['update_course_progress'] ) && 1 === intval( $_POST['update_course_progress'] );

		global $wpdb;
		$activity_table = $wpdb->prefix . 'learndash_user_activity';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$attempts = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$activity_table} WHERE post_id = %d AND activity_type = 'quiz' ORDER BY activity_completed DESC",
			$quiz_id
		) );

		$filtered_attempts = array();
		if ( ! empty( $attempts ) ) {
			foreach ( $attempts as $attempt ) {
				$user = get_userdata( $attempt->user_id );
				if ( ! $user ) {
					continue;
				}

				if ( $group_id > 0 && function_exists( 'learndash_is_user_in_group' ) && ! learndash_is_user_in_group( $user->ID, $group_id ) ) {
					continue;
				}

				if ( ! empty( $search_query ) ) {
					if ( false === stripos( $user->display_name, $search_query )
						&& false === stripos( $user->user_email, $search_query )
						&& false === stripos( $user->user_login, $search_query ) ) {
						continue;
					}
				}
				$filtered_attempts[] = $attempt;
			}
		}

		$total_filtered = count( $filtered_attempts );
		$chunk          = array_slice( $filtered_attempts, $offset, $limit );

		$quiz_meta          = get_post_meta( $quiz_id, '_sfwd-quiz', true );
		$passing_percentage = isset( $quiz_meta['sfwd-quiz_passingpercentage'] ) ? floatval( $quiz_meta['sfwd-quiz_passingpercentage'] ) : 0;
		$pro_quiz_id        = get_post_meta( $quiz_id, 'quiz_pro_id', true );

		$processed = 0;
		foreach ( $chunk as $activity ) {
			$activity_meta    = $this->get_activity_meta( $activity->activity_id );
			$statistic_ref_id = isset( $activity_meta['statistic_ref_id'] ) ? intval( $activity_meta['statistic_ref_id'] ) : 0;

			$calculated_data = $this->calculate_actual_score( $activity, $quiz_id, $statistic_ref_id );

			$data = array(
				'activity_id'      => $activity->activity_id,
				'new_score'        => $calculated_data['score'],
				'total_points'     => $calculated_data['total_points'],
				'question_results' => $calculated_data['question_results'],
				'statistic_ref_id' => $statistic_ref_id,
			);

			if ( $this->update_single_attempt_score( $data, $quiz_id, $pro_quiz_id, $passing_percentage, $update_course ) ) {
				$processed++;
			}
		}

		$completed = ( $offset + count( $chunk ) ) >= $total_filtered;

		wp_send_json_success( array(
			'processed' => count( $chunk ),
			'completed' => $completed,
		) );
	}
}

// Initialize the plugin.
add_action( 'plugins_loaded', function () {
	new LD_Advanced_Regrader();
} );
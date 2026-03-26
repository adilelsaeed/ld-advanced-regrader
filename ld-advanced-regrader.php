<?php
/**
 * Plugin Name: Advanced Regrader for LearnDash
 * Description: Advanced interface for recalculating quiz grades with a preview before saving.
 * Version: 1.0.2
 * Author: Adil Elsaeed
 * Author URI: https://adilelsaeed.com/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ld-advanced-regrader
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4    
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LD_Advanced_Regrader {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'wp_ajax_ld_regrade_batch_process', array( $this, 'ajax_batch_process' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'ld-advanced-regrader', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'learndash-lms',
            esc_html__( 'Regrade Scores', 'ld-advanced-regrader' ),
            esc_html__( 'Regrade Scores', 'ld-advanced-regrader' ),
            'manage_options',
            'ld-advanced-regrader',
            array( $this, 'render_admin_page' )
        );
    }

    public function enqueue_admin_styles() {
        // Enhanced interface styling
        echo '<style>
            .ld-regrade-container { margin-top: 20px; }
            .ld-regrade-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .ld-regrade-table th, .ld-regrade-table td { border: 1px solid #e1e1e1; padding: 12px; }
            .ld-regrade-table th { background: #f8f9fa; font-weight: 600; }
            .score-diff-positive { color: #27ae60; font-weight: bold; background: #e8f5e9; padding: 2px 6px; border-radius: 4px; }
            .score-diff-negative { color: #c0392b; font-weight: bold; background: #ffebee; padding: 2px 6px; border-radius: 4px; }
            .score-diff-neutral { color: #7f8c8d; }
            .search-box-container { margin-bottom: 15px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; }
            .search-box-container input[type="text"] { width: 300px; padding: 8px; }
            .ld-regrade-pagination { margin-top: 15px; display: flex; justify-content: flex-end; align-items: center; gap: 5px; }
            .ld-regrade-pagination .page-numbers { display: inline-block; min-width: 32px; height: 32px; line-height: 30px; text-align: center; border: 1px solid #c3c4c7; background: #f6f7f7; color: #2271b1; text-decoration: none; border-radius: 3px; padding: 0 10px; box-sizing: border-box; font-size: 13px; }
            .ld-regrade-pagination .page-numbers:hover { background: #f0f0f1; border-color: #8c8f94; color: #135e96; }
            .ld-regrade-pagination .page-numbers.current { background: #2271b1; border-color: #2271b1; color: #fff; pointer-events: none; }
        </style>';
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        
        // Process the actual update request (when the save button is clicked)
        if ( isset( $_POST['confirm_regrade'] ) && check_admin_referer( 'ld_regrade_save_action', 'ld_regrade_save_nonce' ) ) {
            $this->save_new_scores( $_POST['users_to_update'], $_POST['quiz_id'] );
        }

        $selected_quiz_id = isset( $_REQUEST['quiz_id'] ) ? intval( $_REQUEST['quiz_id'] ) : 0;
        $selected_group_id = isset( $_REQUEST['group_id'] ) ? intval( $_REQUEST['group_id'] ) : 0;
        $search_query = isset( $_REQUEST['user_search'] ) ? sanitize_text_field( $_REQUEST['user_search'] ) : '';
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
                                    $quizzes = get_posts( array( 'post_type' => 'sfwd-quiz', 'posts_per_page' => -1 ) );
                                    foreach ( $quizzes as $quiz ) {
                                        echo '<option value="' . $quiz->ID . '" ' . selected( $selected_quiz_id, $quiz->ID, false ) . '>' . esc_html( $quiz->post_title ) . '</option>';
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
                                    $groups = get_posts( array( 'post_type' => 'groups', 'posts_per_page' => -1 ) );
                                    foreach ( $groups as $group ) {
                                        echo '<option value="' . $group->ID . '" ' . selected( $selected_group_id, $group->ID, false ) . '>' . esc_html( $group->post_title ) . '</option>';
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
            // If a quiz is selected, display the table
            if ( $selected_quiz_id > 0 ) {
                $this->render_preview_table( $selected_quiz_id, $search_query, $selected_group_id );
            }
            ?>
        </div>
        <?php
    }

    /**
     * Display preview table (Current score vs Expected score)
     */
    private function render_preview_table( $quiz_id, $search_query = '', $group_id = 0 ) {
        global $wpdb;
        
        // Fetch all attempts for students
        $activity_table = $wpdb->prefix . 'learndash_user_activity';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $activity_table 
            WHERE post_id = %d AND activity_type = 'quiz'
            ORDER BY activity_completed DESC",
            $quiz_id
        );

        $attempts = $wpdb->get_results( $sql );
        
        if ( empty( $attempts ) ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'No students found for this quiz.', 'ld-advanced-regrader' ) . '</p></div>';
            return;
        }

        // Apply search if present
        $filtered_attempts = [];
        foreach ( $attempts as $attempt ) {
            $user = get_userdata( $attempt->user_id );
            if ( ! $user ) continue;

            // Filter by Group
            if ( $group_id > 0 ) {
                if ( ! learndash_is_user_in_group( $user->ID, $group_id ) ) {
                    continue;
                }
            }

            if ( ! empty( $search_query ) ) {
                if ( stripos( $user->display_name, $search_query ) === false && 
                     stripos( $user->user_email, $search_query ) === false && 
                     stripos( $user->user_login, $search_query ) === false ) {
                    continue;
                }
            }
            $filtered_attempts[] = $attempt;
        }

        if ( empty( $filtered_attempts ) ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'No results match your search.', 'ld-advanced-regrader' ) . '</p></div>';
            return;
        }

        $total_items = count( $filtered_attempts );
        $per_page = 50;
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        
        $page_attempts = array_slice( $filtered_attempts, ( $current_page - 1 ) * $per_page, $per_page );

        echo '<div class="notice notice-info"><p>' . sprintf( esc_html__( 'Showing %d to %d of %d attempts.', 'ld-advanced-regrader' ), ( $current_page - 1 ) * $per_page + 1, min( $current_page * $per_page, $total_items ), $total_items ) . '</p></div>';

        echo '<form method="post" action="">';
        wp_nonce_field( 'ld_regrade_save_action', 'ld_regrade_save_nonce' );
        echo '<input type="hidden" name="quiz_id" value="' . $quiz_id . '">';
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
            $user = get_userdata( $user_id );
            
            $activity_meta = $this->get_activity_meta( $activity->activity_id );
            
            $current_score = isset( $activity_meta['score'] ) ? intval( $activity_meta['score'] ) : 0;
            
            $statistic_ref_id = isset( $activity_meta['statistic_ref_id'] ) ? intval( $activity_meta['statistic_ref_id'] ) : 0;

            // Calculate the actual score (Re-calculation Logic)
            $calculated_data = $this->calculate_actual_score( $activity, $quiz_id, $statistic_ref_id );
            $new_score = $calculated_data['score'];
            
            // Determine the difference
            $diff = $new_score - $current_score;
            $diff_class = 'score-diff-neutral';
            $diff_text = '0';
            
            if ( $diff > 0 ) {
                $diff_class = 'score-diff-positive';
                $diff_text = '+' . $diff;
            } elseif ( $diff < 0 ) {
                $diff_class = 'score-diff-negative';
                $diff_text = $diff;
            }

            // Include new score data in a hidden field to pass it during save
            $input_data = base64_encode( json_encode( array( 
                'activity_id' => $activity->activity_id, 
                'new_score' => $new_score,
                'total_points' => $calculated_data['total_points'],
                'question_results' => $calculated_data['question_results'],
                'statistic_ref_id' => $statistic_ref_id
            ) ) );

            $new_total_points = intval( $calculated_data['total_points'] );
            $new_percentage = ( $new_total_points > 0 ) ? round( ( $new_score / $new_total_points ) * 100, 2 ) : 0;

            $current_percentage = isset( $activity_meta['percentage'] ) ? floatval( $activity_meta['percentage'] ) : 0;
            // Fallback calculation if percentage is missing but we have points
            if($current_percentage == 0 && isset($activity_meta['points']) && intval($activity_meta['points']) > 0) {
                 $current_percentage = round( ( $current_score / intval($activity_meta['points']) ) * 100, 2 );
            }

            echo '<tr>';
            echo '<td><input type="checkbox" name="users_to_update[]" value="' . $input_data . '"></td>';
            echo '<td>' . ( $user ? esc_html( $user->display_name ) : esc_html__( 'Deleted User', 'ld-advanced-regrader' ) ) . ' (' . $user_id . ')</td>';
            echo '<td>' . date( 'Y-m-d H:i', $activity->activity_completed ) . '</td>';
            echo '<td>' . $current_score . '</td>';
            echo '<td>' . $current_percentage . '%</td>';
            echo '<td><strong>' . $new_score . '</strong></td>';
            echo '<td><strong>' . $new_percentage . '%</strong></td>';
            echo '<td class="' . $diff_class . '">' . $diff_text . '</td>';
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
        echo '<div class="ld-regrade-pagination">' . paginate_links( $pagination_args ) . '</div>';

        echo '<p style="margin-top:15px;">
                <label style="margin-left: 15px;">
                    <input type="checkbox" name="update_course_progress" id="update_course_progress" value="1">
                    ' . esc_html__( 'Update Course Completion (if student passes)', 'ld-advanced-regrader' ) . '
                </label>
                <button type="submit" id="btn-update-selected" class="button button-primary button-large">' . esc_html__( 'Update Selected Students Grades', 'ld-advanced-regrader' ) . '</button>
                <button type="button" id="btn-regrade-all" class="button button-secondary button-large" style="margin-left:10px;">' . esc_html__( 'Auto Regrade All Matching (Batch)', 'ld-advanced-regrader' ) . '</button>
              </p>';
        
        echo '<div id="batch-progress-container" style="display:none; margin-top:20px; padding:15px; background:#fff; border:1px solid #ccc;">
                <h4>' . esc_html__( 'Batch Regrading Progress', 'ld-advanced-regrader' ) . '</h4>
                <progress id="batch-progress-bar" value="0" max="' . $total_items . '" style="width:100%; height:20px;"></progress>
                <p id="batch-progress-text">0 / ' . $total_items . '</p>
              </div>';

        echo '<script>
            document.getElementById("select-all").onclick = function() {
                var checkboxes = document.querySelectorAll("input[name=\'users_to_update[]\']");
                for (var checkbox of checkboxes) {
                    checkbox.checked = this.checked;
                }
            }

            jQuery(document).ready(function($) {
                $("#btn-regrade-all").on("click", function(e) {
                    e.preventDefault();
                    if(!confirm("' . esc_js( __( 'Are you sure you want to recalculate and save scores for ALL matching attempts? This may take some time.', 'ld-advanced-regrader' ) ) . '")) return;
                    
                    $("#btn-update-selected, #btn-regrade-all").prop("disabled", true);
                    $("#batch-progress-container").show();
                    
                    var totalItems = ' . $total_items . ';
                    var quizId = ' . $quiz_id . ';
                    var groupId = ' . $group_id . ';
                    var searchQuery = "' . esc_js( $search_query ) . '";
                    var updateCourse = $("#update_course_progress").is(":checked") ? 1 : 0;
                    
                    function processBatch(offset) {
                        $.post(ajaxurl, {
                            action: "ld_regrade_batch_process",
                            quiz_id: quizId,
                            group_id: groupId,
                            search_query: searchQuery,
                            update_course_progress: updateCourse,
                            offset: offset,
                            nonce: "' . wp_create_nonce( 'ld_regrade_batch_nonce' ) . '"
                        }, function(response) {
                            if(response && response.success) {
                                var newOffset = offset + response.data.processed;
                                $("#batch-progress-bar").val(newOffset);
                                $("#batch-progress-text").text(newOffset + " / " + totalItems);
                                
                                if(response.data.completed) {
                                    $("#batch-progress-text").text("' . esc_js( __( 'Completed!', 'ld-advanced-regrader' ) ) . '");
                                    setTimeout(function() { location.reload(); }, 1500);
                                } else {
                                    processBatch(newOffset);
                                }
                            } else {
                                alert("' . esc_js( __( 'An error occurred during batch processing.', 'ld-advanced-regrader' ) ) . '");
                                $("#btn-update-selected, #btn-regrade-all").prop("disabled", false);
                            }
                        }).fail(function() {
                            alert("' . esc_js( __( 'Server error during batch processing. Please check your server logs.', 'ld-advanced-regrader' ) ) . '");
                            $("#btn-update-selected, #btn-regrade-all").prop("disabled", false);
                        });
                    }
                    
                    processBatch(0);
                });
            });
        </script>';
        echo '</form>';
    }

    /**
     * Core function: Calculate the actual score based on the current question settings
     */
    private function calculate_actual_score( $activity, $quiz_id, $statistic_ref_id = 0 ) {
        global $wpdb;
        
        // 1. Determine the ProQuiz Master ID associated with the LearnDash Quiz
        $pro_quiz_id = get_post_meta( $quiz_id, 'quiz_pro_id', true );
        // print_r("Quiz ID: ".$quiz_id." : ".$pro_quiz_id);
        if ( ! $pro_quiz_id ) {
            return ['score' => 0, 'total_points' => 0];
        }

        // 2. جلب جميع الأسئلة الحالية لهذا الاختبار
        $question_mapper = new WpProQuiz_Model_QuestionMapper();
        $questions = $question_mapper->fetchAll( $pro_quiz_id ); // Current questions with their current settings (after modification)
        // 3. Fetch user answers from statistics tables
        // Attempt to link via statistic_ref_id
        $statistic_ref_mapper = new WpProQuiz_Model_StatisticRefMapper();
        
        $stat_table = $wpdb->prefix . 'learndash_pro_quiz_statistic';
        $ref_table = $wpdb->prefix . 'learndash_pro_quiz_statistic_ref';
        
        if ( $statistic_ref_id > 0 ) {
            $user_stats = $wpdb->get_results( $wpdb->prepare(
                "SELECT question_id, answer_data FROM $stat_table 
                WHERE statistic_ref_id = %d",
                $statistic_ref_id
            ) );
        } else {
            $user_stats = $wpdb->get_results( $wpdb->prepare(
                "SELECT question_id, answer_data FROM $stat_table 
                WHERE statistic_ref_id = (
                    SELECT statistic_ref_id FROM $ref_table 
                    WHERE user_id = %d AND quiz_id = %d AND ABS(create_time - %d) <= 60
                    ORDER BY create_time DESC LIMIT 1
                )",
                $activity->user_id, $pro_quiz_id, $activity->activity_completed
            ) );
        }

        // Map user answers for easier search by question ID
        $user_answers_map = [];
        foreach ( $user_stats as $stat ) {
            $data = maybe_unserialize( $stat->answer_data );
            if ( is_string( $data ) ) {
                $decoded = json_decode( $data, true );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $data = $decoded;
                }
            }
            $user_answers_map[ $stat->question_id ] = $data;
        }
        // print_r($user_answers_map);
        $calculated_score = 0;
        $total_possible_points = 0;
        $question_results = []; // Per-question results for updating pro_quiz_statistic

        // 4. Compare user answers with current question settings
        foreach ( $questions as $question ) {
            $q_id = $question->getId();
            $points = $question->getPoints();
            
            // Increment total possible points based on CURRENT available questions
            // This ensures that if new questions were added after the attempt, they are included in the new total (and percentage).
            $total_possible_points += $points;

            if ( isset( $user_answers_map[ $q_id ] ) ) {
                $user_answer_data = $user_answers_map[ $q_id ];
                $type = $question->getAnswerType();
                
                $is_correct = false;
                
                // Dispatch based on question type
                switch ( $type ) {
                    case 'single':
                        $is_correct = $this->is_correct_single( $question, $user_answer_data );
                        break;
                    case 'multiple':
                        $is_correct = $this->is_correct_multiple( $question, $user_answer_data );
                        break;
                    default:
                        // Other types can be supported in the future
                        $is_correct = false;
                        break;
                }

                if ( $is_correct ) {
                    $calculated_score += $points;
                }

                // Store per-question result for statistic table update
                $question_results[ $q_id ] = [
                    'correct' => $is_correct,
                    'points' => $is_correct ? $points : 0
                ];
            }
        }
        return [
            'score' => $calculated_score, 
            'total_points' => $total_possible_points,
            'question_results' => $question_results
        ];
    }

    /**
     * Validate "Single Choice" answer correctness
     */
    private function is_correct_single( $question, $user_answer_data ) {
        $answer_data = $question->getAnswerData();
        
        foreach ( $answer_data as $index => $answer_obj ) {
            $should_be_selected = $answer_obj->isCorrect();
            $is_selected = false;

            if ( is_array( $user_answer_data ) ) {
                if ( isset( $user_answer_data[$index] ) && $user_answer_data[$index] == 1 ) {
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
     * Validate "Multiple Choice" answer correctness
     */
    private function is_correct_multiple( $question, $user_answer_data ) {
        $answer_data = $question->getAnswerData();
        
        foreach ( $answer_data as $index => $answer_obj ) {
            $should_be_selected = $answer_obj->isCorrect();
            $is_selected = false;

            if ( is_array( $user_answer_data ) ) {
                if ( isset( $user_answer_data[$index] ) && $user_answer_data[$index] == 1 ) {
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
     * Save new scores
     */
    private function save_new_scores( $encoded_data_array, $quiz_id ) {
        if ( empty( $encoded_data_array ) ) return;

        $count = 0;
        $update_course_progress = isset( $_POST['update_course_progress'] );

        // Initial meta caching for the whole batch
        $quiz_meta = get_post_meta( $quiz_id, '_sfwd-quiz', true );
        $passing_percentage = isset( $quiz_meta['sfwd-quiz_passingpercentage'] ) ? floatval( $quiz_meta['sfwd-quiz_passingpercentage'] ) : 0;
        $pro_quiz_id = get_post_meta( $quiz_id, 'quiz_pro_id', true );

        foreach ( $encoded_data_array as $encoded_item ) {
            $data = json_decode( base64_decode( $encoded_item ), true );
            if ( $this->update_single_attempt_score( $data, $quiz_id, $pro_quiz_id, $passing_percentage, $update_course_progress ) ) {
                $count++;
            }
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Successfully updated scores for %d students.', 'ld-advanced-regrader' ), $count ) . '</p></div>';
    }

    /**
     * Helper: Reusable update logic suitable for Ajax processing or manual processing
     */
    private function update_single_attempt_score( $data, $quiz_id, $pro_quiz_id, $passing_percentage, $update_course_progress ) {
        global $wpdb;
        $activity_table = $wpdb->prefix . 'learndash_user_activity';
        
        $activity_id = isset( $data['activity_id'] ) ? intval( $data['activity_id'] ) : 0;
        if ( ! $activity_id ) return false;
        
        $new_score = intval( $data['new_score'] );
        $total_points = intval( $data['total_points'] );
        $statistic_ref_id = isset( $data['statistic_ref_id'] ) ? intval( $data['statistic_ref_id'] ) : 0;

        $activity = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $activity_table WHERE activity_id = %d", $activity_id ) );
        if ( ! $activity ) return false;
        
        $user_id = $activity->user_id;
        $course_id = $activity->course_id;

        $activity_meta = $this->get_activity_meta( $activity_id );
        $old_score = isset( $activity_meta['score'] ) ? intval( $activity_meta['score'] ) : 0;
        
        $activity_meta['regraded'] = 1;
        $activity_meta['regraded_date'] = current_time( 'mysql' );
        $activity_meta['original_score'] = $old_score;

        $new_percentage = 0;
        if ( $total_points > 0 ) {
            $new_percentage = round( ($new_score / $total_points) * 100, 2 );
        }

        $new_pass = ( $new_percentage >= $passing_percentage ) ? 1 : 0;

        $activity_meta['score'] = $new_score;
        $activity_meta['points'] = $new_score;
        $activity_meta['total_points'] = $total_points;
        $activity_meta['percentage'] = $new_percentage;
        $activity_meta['pass'] = $new_pass;

        $this->update_activity_meta( $activity_id, $activity_meta );
        
        $wpdb->update(
            $activity_table,
            array( 'activity_status' => $new_pass ),
            array( 'activity_id' => $activity_id ),
            array( '%d' ),
            array( '%d' )
        );

        $this->update_user_quiz_meta( $user_id, $quiz_id, $pro_quiz_id, $activity, $new_score, $total_points, $new_percentage, $new_pass, $statistic_ref_id );

        $question_results = isset( $data['question_results'] ) ? $data['question_results'] : [];
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
     * This is the primary data source for quiz statistics in LearnDash.
     */
    private function update_user_quiz_meta( $user_id, $quiz_id, $pro_quiz_id, $activity, $new_score, $total_points, $new_percentage, $new_pass, $statistic_ref_id = 0 ) {
        $user_quiz_meta = get_user_meta( $user_id, '_sfwd-quizzes', true );
        if ( ! is_array( $user_quiz_meta ) ) {
            return;
        }

        $updated = false;
        // Find the matching quiz attempt(s) and update them
        // Match by quiz post ID and time (activity_completed)
        foreach ( $user_quiz_meta as $key => $quiz_attempt ) {
            if ( ! isset( $quiz_attempt['quiz'] ) ) continue;
            
            // Match by quiz post ID
            if ( intval( $quiz_attempt['quiz'] ) !== intval( $quiz_id ) ) continue;
            
            // Match by time if available (to find correct attempt)
            if ( isset( $quiz_attempt['time'] ) && $activity->activity_completed > 0 ) {
                // Allow small time difference (within 60 seconds)
                if ( abs( intval( $quiz_attempt['time'] ) - intval( $activity->activity_completed ) ) > 60 ) {
                    continue;
                }
            }
            
            // Exact match via statistic_ref_id if possible
            $matched = false;
            if ( $statistic_ref_id > 0 && isset( $quiz_attempt['statistic_ref_id'] ) && intval( $quiz_attempt['statistic_ref_id'] ) === $statistic_ref_id ) {
                $matched = true;
            } elseif ( isset( $quiz_attempt['time'] ) && $activity->activity_completed > 0 ) {
                if ( abs( intval( $quiz_attempt['time'] ) - intval( $activity->activity_completed ) ) <= 60 ) {
                    $matched = true;
                }
            }

            // If we still couldn't match, try the last attempt for this quiz
            if ( ! $matched ) {
                continue;
            }

            // Update the quiz attempt data
            $user_quiz_meta[ $key ]['score'] = $new_score;
            $user_quiz_meta[ $key ]['points'] = $new_score;
            $user_quiz_meta[ $key ]['total_points'] = $total_points;
            $user_quiz_meta[ $key ]['percentage'] = $new_percentage;
            $user_quiz_meta[ $key ]['pass'] = $new_pass;
            $user_quiz_meta[ $key ]['m_edit_by'] = get_current_user_id();
            $user_quiz_meta[ $key ]['m_edit_time'] = time();
            $updated = true;
            break; // Update only the matching attempt
        }

        // If no match found by time, update the last attempt for this quiz
        if ( ! $updated ) {
            for ( $i = count( $user_quiz_meta ) - 1; $i >= 0; $i-- ) {
                if ( isset( $user_quiz_meta[ $i ]['quiz'] ) && intval( $user_quiz_meta[ $i ]['quiz'] ) === intval( $quiz_id ) ) {
                    $user_quiz_meta[ $i ]['score'] = $new_score;
                    $user_quiz_meta[ $i ]['points'] = $new_score;
                    $user_quiz_meta[ $i ]['total_points'] = $total_points;
                    $user_quiz_meta[ $i ]['percentage'] = $new_percentage;
                    $user_quiz_meta[ $i ]['pass'] = $new_pass;
                    $user_quiz_meta[ $i ]['m_edit_by'] = get_current_user_id();
                    $user_quiz_meta[ $i ]['m_edit_time'] = time();
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
     * Update the wp_learndash_pro_quiz_statistic table per-question.
     * This is the data source for the Quiz Statistics page (ldAdvQuiz statistics).
     */
    private function update_pro_quiz_statistics( $user_id, $pro_quiz_id, $question_results, $statistic_ref_id = 0, $activity_completed = 0 ) {
        global $wpdb;
        
        $stat_table = $wpdb->prefix . 'learndash_pro_quiz_statistic';
        $ref_table = $wpdb->prefix . 'learndash_pro_quiz_statistic_ref';

        $ref_id = $statistic_ref_id;

        // If no explicit ref_id, attempt to find it
        if ( ! $ref_id ) {
            $ref_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT statistic_ref_id FROM $ref_table 
                WHERE user_id = %d AND quiz_id = %d AND ABS(create_time - %d) <= 60
                ORDER BY create_time DESC LIMIT 1",
                $user_id, $pro_quiz_id, $activity_completed
            ) );
        }

        if ( ! $ref_id ) {
            return;
        }

        // Update each question's statistics
        foreach ( $question_results as $question_id => $result ) {
            $question_id = intval( $question_id );
            $is_correct = $result['correct'];
            $points = floatval( $result['points'] );

            // Check if row exists
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $stat_table WHERE statistic_ref_id = %d AND question_id = %d",
                $ref_id, $question_id
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
     * Fetch meta from learndash_user_activity_meta table
     */
    private function get_activity_meta( $activity_id ) {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'learndash_user_activity_meta';
        $results = $wpdb->get_results( $wpdb->prepare( 
            "SELECT activity_meta_key, activity_meta_value FROM $meta_table WHERE activity_id = %d", 
            $activity_id 
        ) );
        
        $meta = [];
        if ( ! empty( $results ) ) {
            foreach ( $results as $row ) {
                $meta[ $row->activity_meta_key ] = maybe_unserialize( $row->activity_meta_value );
            }
        }
        return $meta;
    }

    /**
     * Update meta in learndash_user_activity_meta table
     */
    private function update_activity_meta( $activity_id, $meta_data ) {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'learndash_user_activity_meta';
        
        foreach ( $meta_data as $key => $value ) {
            $serialized_data = maybe_serialize( $value );
            
            $exists = $wpdb->get_var( $wpdb->prepare( 
                "SELECT activity_meta_id FROM $meta_table WHERE activity_id = %d AND activity_meta_key = %s", 
                $activity_id, $key
            ) );

            if ( $exists ) {
                $wpdb->update( 
                    $meta_table, 
                    array( 'activity_meta_value' => $serialized_data ), 
                    array( 'activity_id' => $activity_id, 'activity_meta_key' => $key ) 
                );
            } else {
                $wpdb->insert( 
                    $meta_table, 
                    array( 
                        'activity_id'         => $activity_id, 
                        'activity_meta_key'    => $key, 
                        'activity_meta_value'  => $serialized_data 
                    ) 
                );
            }
        }
    }

    /**
     * AJAX Handler for batch processing attempts
     */
    public function ajax_batch_process() {
        check_ajax_referer( 'ld_regrade_batch_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
        $group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
        $search_query = isset( $_POST['search_query'] ) ? sanitize_text_field( $_POST['search_query'] ) : '';
        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $limit = 50; // safe chunk size
        $update_course = isset( $_POST['update_course_progress'] ) && $_POST['update_course_progress'] == 1;

        global $wpdb;
        $activity_table = $wpdb->prefix . 'learndash_user_activity';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $activity_table 
            WHERE post_id = %d AND activity_type = 'quiz'
            ORDER BY activity_completed DESC",
            $quiz_id
        );

        $attempts = $wpdb->get_results( $sql );
        
        $filtered_attempts = [];
        if ( ! empty( $attempts ) ) {
            foreach ( $attempts as $attempt ) {
                $user = get_userdata( $attempt->user_id );
                if ( ! $user ) continue;

                if ( $group_id > 0 && ! learndash_is_user_in_group( $user->ID, $group_id ) ) {
                    continue;
                }

                if ( ! empty( $search_query ) ) {
                    if ( stripos( $user->display_name, $search_query ) === false && 
                         stripos( $user->user_email, $search_query ) === false && 
                         stripos( $user->user_login, $search_query ) === false ) {
                        continue;
                    }
                }
                $filtered_attempts[] = $attempt;
            }
        }

        $total_filtered = count( $filtered_attempts );
        $chunk = array_slice( $filtered_attempts, $offset, $limit );

        // Prepare meta fetching and global settings
        $quiz_meta = get_post_meta( $quiz_id, '_sfwd-quiz', true );
        $passing_percentage = isset( $quiz_meta['sfwd-quiz_passingpercentage'] ) ? floatval( $quiz_meta['sfwd-quiz_passingpercentage'] ) : 0;
        $pro_quiz_id = get_post_meta( $quiz_id, 'quiz_pro_id', true );
        
        $processed = 0;
        foreach ( $chunk as $activity ) {
            $activity_meta = $this->get_activity_meta( $activity->activity_id );
            $statistic_ref_id = isset( $activity_meta['statistic_ref_id'] ) ? intval( $activity_meta['statistic_ref_id'] ) : 0;
            
            $calculated_data = $this->calculate_actual_score( $activity, $quiz_id, $statistic_ref_id );
            
            $data = array(
                'activity_id' => $activity->activity_id,
                'new_score' => $calculated_data['score'],
                'total_points' => $calculated_data['total_points'],
                'question_results' => $calculated_data['question_results'],
                'statistic_ref_id' => $statistic_ref_id
            );
            
            if ( $this->update_single_attempt_score( $data, $quiz_id, $pro_quiz_id, $passing_percentage, $update_course ) ) {
                $processed++;
            }
        }

        // We determine completion if our offset + chunk limit >= total items, 
        // Or if the chunk was empty
        $completed = ( $offset + count( $chunk ) ) >= $total_filtered;

        wp_send_json_success( array(
            'processed' => count( $chunk ),
            'completed' => $completed
        ) );
    }
}

new LD_Advanced_Regrader();
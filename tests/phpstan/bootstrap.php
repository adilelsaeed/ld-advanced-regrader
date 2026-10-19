<?php
/**
 * PHPStan bootstrap: constants and LearnDash symbols the plugin uses.
 *
 * LearnDash is commercial and not on Packagist, so its few symbols are
 * declared here for static analysis only.
 *
 * @package AdilElsaeed\LDRegrader
 */

// phpcs:disable

define( 'LDAR_VERSION', '2.0.0' );
define( 'LDAR_PLUGIN_FILE', __FILE__ );
define( 'LDAR_PLUGIN_DIR', '' );
define( 'LDAR_PLUGIN_URL', '' );
define( 'LDAR_PLUGIN_BASENAME', '' );

class WpProQuiz_Model_AnswerTypes {
	/** @return bool */
	public function isCorrect() {
		return false;
	}
}

class WpProQuiz_Model_Question {
	/** @return int */
	public function getId() {
		return 0;
	}

	/** @return int */
	public function getPoints() {
		return 0;
	}

	/** @return string */
	public function getAnswerType() {
		return '';
	}

	/** @return WpProQuiz_Model_AnswerTypes[] */
	public function getAnswerData() {
		return array();
	}
}

class WpProQuiz_Model_QuestionMapper {
	/**
	 * @param int $quiz_id ProQuiz ID.
	 * @return WpProQuiz_Model_Question[]
	 */
	public function fetchAll( $quiz_id ) {
		return array();
	}
}

/**
 * @param int  $user_id   User ID.
 * @param int  $post_id   Step ID.
 * @param bool $only_calc Only calculate.
 * @param int  $course_id Course ID.
 * @return bool
 */
function learndash_process_mark_complete( $user_id = null, $post_id = null, $only_calc = false, $course_id = 0 ) {
	return true;
}

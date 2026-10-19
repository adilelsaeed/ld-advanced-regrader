<?php
/**
 * Quiz repository.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Data;

use AdilElsaeed\LDRegrader\Scoring\Question;

/**
 * Reads quiz settings and questions from LearnDash.
 *
 * The only class that touches WpProQuiz models, so everything else works
 * with plain Question objects.
 *
 * @since 2.0.0
 */
class QuizRepository {

	/**
	 * Questions cached per ProQuiz ID for the current request.
	 *
	 * @var array<int, Question[]>
	 */
	private $questions = array();

	/**
	 * ProQuiz ID linked to a LearnDash quiz.
	 *
	 * @param int $quiz_id Quiz post ID.
	 * @return int 0 when not linked.
	 */
	public function get_pro_quiz_id( int $quiz_id ): int {
		return (int) get_post_meta( $quiz_id, 'quiz_pro_id', true );
	}

	/**
	 * Passing percentage configured on a quiz.
	 *
	 * @param int $quiz_id Quiz post ID.
	 * @return float
	 */
	public function get_passing_percentage( int $quiz_id ): float {
		$settings = get_post_meta( $quiz_id, '_sfwd-quiz', true );

		return is_array( $settings ) && isset( $settings['sfwd-quiz_passingpercentage'] )
			? (float) $settings['sfwd-quiz_passingpercentage']
			: 0.0;
	}

	/**
	 * Current questions of a ProQuiz quiz.
	 *
	 * @param int $pro_quiz_id ProQuiz ID.
	 * @return Question[]
	 */
	public function get_questions( int $pro_quiz_id ): array {
		if ( $pro_quiz_id <= 0 || ! class_exists( 'WpProQuiz_Model_QuestionMapper' ) ) {
			return array();
		}

		if ( isset( $this->questions[ $pro_quiz_id ] ) ) {
			return $this->questions[ $pro_quiz_id ];
		}

		$mapper    = new \WpProQuiz_Model_QuestionMapper();
		$questions = array();

		foreach ( (array) $mapper->fetchAll( $pro_quiz_id ) as $model ) {
			$flags = array();
			foreach ( (array) $model->getAnswerData() as $index => $answer ) {
				$flags[ $index ] = (bool) $answer->isCorrect();
			}

			$questions[] = new Question( (int) $model->getId(), (int) $model->getPoints(), (string) $model->getAnswerType(), $flags );
		}

		$this->questions[ $pro_quiz_id ] = $questions;

		return $questions;
	}

	/**
	 * Quizzes for the filter dropdown.
	 *
	 * @return array<int, array{id: int, title: string}>
	 */
	public function list_quizzes(): array {
		return $this->list_posts( 'sfwd-quiz' );
	}

	/**
	 * LearnDash groups for the filter dropdown.
	 *
	 * @return array<int, array{id: int, title: string}>
	 */
	public function list_groups(): array {
		return $this->list_posts( 'groups' );
	}

	/**
	 * ID and title of every post of a type.
	 *
	 * @param string $post_type Post type.
	 * @return array<int, array{id: int, title: string}>
	 */
	private function list_posts( string $post_type ): array {
		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => array( 'publish', 'draft', 'private' ),
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		return array_map(
			static function ( $post ) {
				return array(
					'id'    => (int) $post->ID,
					'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				);
			},
			$posts
		);
	}
}

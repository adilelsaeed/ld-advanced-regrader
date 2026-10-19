<?php
/**
 * Question value object.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Scoring;

/**
 * A quiz question as the regrader needs it: decoupled from WpProQuiz models
 * so scoring logic can be tested without LearnDash loaded.
 *
 * @since 2.0.0
 */
class Question {

	/**
	 * ProQuiz question ID.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Points awarded for a correct answer.
	 *
	 * @var int
	 */
	public $points;

	/**
	 * ProQuiz answer type (single, multiple, free_answer, sort_answer, ...).
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Whether each answer option is correct, keyed by option index.
	 *
	 * @var array<int|string, bool>
	 */
	public $correct_flags;

	/**
	 * Constructor.
	 *
	 * @param int                     $id            Question ID.
	 * @param int                     $points        Points for a correct answer.
	 * @param string                  $type          Answer type.
	 * @param array<int|string, bool> $correct_flags Correct flag per option index.
	 */
	public function __construct( int $id, int $points, string $type, array $correct_flags ) {
		$this->id            = $id;
		$this->points        = $points;
		$this->type          = $type;
		$this->correct_flags = $correct_flags;
	}
}

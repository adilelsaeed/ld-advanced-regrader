<?php
/**
 * Score calculator.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Scoring;

/**
 * Re-scores an attempt: compares the answers a student gave at the time
 * against the quiz's current questions, answers and points.
 *
 * @since 2.0.0
 */
class ScoreCalculator {

	/**
	 * Answer evaluator.
	 *
	 * @var AnswerEvaluator
	 */
	private $evaluator;

	/**
	 * Constructor.
	 *
	 * @param AnswerEvaluator|null $evaluator Answer evaluator.
	 */
	public function __construct( ?AnswerEvaluator $evaluator = null ) {
		$this->evaluator = $evaluator ? $evaluator : new AnswerEvaluator();
	}

	/**
	 * Calculate the new score.
	 *
	 * Questions the student did not answer still count towards the total.
	 * Question types the evaluator cannot re-score keep the points the student
	 * was originally awarded, so a regrade never silently zeroes them.
	 *
	 * @param Question[]                                                        $questions Current quiz questions.
	 * @param array<int, array{answer: mixed, points: int|null, correct: bool|null}> $answers   Stored answers keyed by question ID.
	 * @return ScoreResult
	 */
	public function calculate( array $questions, array $answers ): ScoreResult {
		$score   = 0;
		$total   = 0;
		$results = array();

		foreach ( $questions as $question ) {
			$total += $question->points;

			if ( ! array_key_exists( $question->id, $answers ) ) {
				continue;
			}

			$stored = $answers[ $question->id ];

			if ( $this->evaluator->supports( $question->type ) ) {
				$correct = $this->evaluator->is_correct( $question, $stored['answer'] );
				$points  = $correct ? $question->points : 0;
				$status  = ScoreResult::STATUS_RESCORED;
			} else {
				$points  = isset( $stored['points'] ) ? (int) $stored['points'] : 0;
				$correct = isset( $stored['correct'] ) ? (bool) $stored['correct'] : $points > 0;
				$status  = ScoreResult::STATUS_KEPT;
			}

			$score                   += $points;
			$results[ $question->id ] = array(
				'correct' => $correct,
				'points'  => $points,
				'status'  => $status,
			);
		}

		return new ScoreResult( $score, $total, $results );
	}
}

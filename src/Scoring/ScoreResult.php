<?php
/**
 * Score result value object.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Scoring;

/**
 * The outcome of re-scoring one attempt.
 *
 * @since 2.0.0
 */
class ScoreResult {

	/**
	 * Question result status: re-scored against current settings.
	 */
	const STATUS_RESCORED = 'rescored';

	/**
	 * Question result status: type not supported, original points kept.
	 */
	const STATUS_KEPT = 'kept';

	/**
	 * Points earned.
	 *
	 * @var int
	 */
	public $score;

	/**
	 * Total points available in the quiz.
	 *
	 * @var int
	 */
	public $total_points;

	/**
	 * Per-question results keyed by question ID.
	 *
	 * @var array<int, array{correct: bool, points: int, status: string}>
	 */
	public $question_results;

	/**
	 * Constructor.
	 *
	 * @param int                                                          $score            Points earned.
	 * @param int                                                          $total_points     Points available.
	 * @param array<int, array{correct: bool, points: int, status: string}> $question_results Per-question results.
	 */
	public function __construct( int $score, int $total_points, array $question_results ) {
		$this->score            = $score;
		$this->total_points     = $total_points;
		$this->question_results = $question_results;
	}

	/**
	 * Score as a percentage of total points, rounded to two decimals.
	 *
	 * @return float
	 */
	public function percentage(): float {
		if ( $this->total_points <= 0 ) {
			return 0.0;
		}

		return round( ( $this->score / $this->total_points ) * 100, 2 );
	}

	/**
	 * Whether the attempt passes at the given threshold.
	 *
	 * @param float $passing_percentage Passing percentage (0-100).
	 * @return bool
	 */
	public function passes( float $passing_percentage ): bool {
		return $this->percentage() >= $passing_percentage;
	}

	/**
	 * Number of questions whose original points were kept.
	 *
	 * @return int
	 */
	public function kept_count(): int {
		return count(
			array_filter(
				$this->question_results,
				static function ( $result ) {
					return self::STATUS_KEPT === $result['status'];
				}
			)
		);
	}
}

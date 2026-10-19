<?php
/**
 * Regrader.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Regrade;

use AdilElsaeed\LDRegrader\Data\AttemptQuery;
use AdilElsaeed\LDRegrader\Data\AttemptRepository;
use AdilElsaeed\LDRegrader\Data\QuizRepository;
use AdilElsaeed\LDRegrader\Scoring\ScoreCalculator;
use AdilElsaeed\LDRegrader\Scoring\ScoreResult;

/**
 * Previews and applies new scores for quiz attempts.
 *
 * Scores are always recalculated on the server from the stored answers. The
 * client only sends which attempts to update, never the scores themselves.
 *
 * @since 2.0.0
 */
class Regrader {

	/**
	 * Attempt repository.
	 *
	 * @var AttemptRepository
	 */
	private $attempts;

	/**
	 * Quiz repository.
	 *
	 * @var QuizRepository
	 */
	private $quizzes;

	/**
	 * Score calculator.
	 *
	 * @var ScoreCalculator
	 */
	private $calculator;

	/**
	 * User quiz meta updater.
	 *
	 * @var UserQuizMetaUpdater
	 */
	private $meta_updater;

	/**
	 * Constructor.
	 *
	 * @param AttemptRepository   $attempts     Attempt repository.
	 * @param QuizRepository      $quizzes      Quiz repository.
	 * @param ScoreCalculator     $calculator   Score calculator.
	 * @param UserQuizMetaUpdater $meta_updater User quiz meta updater.
	 */
	public function __construct( AttemptRepository $attempts, QuizRepository $quizzes, ScoreCalculator $calculator, UserQuizMetaUpdater $meta_updater ) {
		$this->attempts     = $attempts;
		$this->quizzes      = $quizzes;
		$this->calculator   = $calculator;
		$this->meta_updater = $meta_updater;
	}

	/**
	 * Preview rows for a page of attempts.
	 *
	 * @param AttemptQuery $query Filters and paging.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function preview( AttemptQuery $query ): array {
		$found = $this->attempts->find( $query );
		$items = array();

		foreach ( $found['items'] as $attempt ) {
			$items[] = $this->preview_row( $query->quiz_id, $attempt );
		}

		return array(
			'items' => $items,
			'total' => $found['total'],
		);
	}

	/**
	 * Compare an attempt's stored score with its recalculated score.
	 *
	 * @param int    $quiz_id Quiz post ID.
	 * @param object $attempt Attempt row (with display_name).
	 * @return array<string, mixed>
	 */
	public function preview_row( int $quiz_id, $attempt ): array {
		$activity_id = (int) $attempt->activity_id;
		$meta        = $this->attempts->get_meta( $activity_id );
		$result      = $this->score( $quiz_id, $attempt, $meta );

		$current_score      = isset( $meta['score'] ) ? (int) $meta['score'] : 0;
		$current_percentage = $this->current_percentage( $meta, $current_score );

		return array(
			'activity_id'        => $activity_id,
			'user_id'            => (int) $attempt->user_id,
			'display_name'       => isset( $attempt->display_name ) ? (string) $attempt->display_name : '',
			'completed'          => (int) $attempt->activity_completed,
			'current_score'      => $current_score,
			'current_percentage' => $current_percentage,
			'current_pass'       => ! empty( $meta['pass'] ),
			'new_score'          => $result->score,
			'new_total'          => $result->total_points,
			'new_percentage'     => $result->percentage(),
			'new_pass'           => $result->passes( $this->quizzes->get_passing_percentage( $quiz_id ) ),
			'difference'         => $result->score - $current_score,
			'kept_questions'     => $result->kept_count(),
			'regraded'           => ! empty( $meta['regraded'] ),
		);
	}

	/**
	 * Recalculate and save the score of one attempt.
	 *
	 * @param int  $quiz_id       Quiz post ID the attempt must belong to.
	 * @param int  $activity_id   Activity ID.
	 * @param bool $update_course Mark the quiz complete when it now passes.
	 * @return bool False when the attempt does not exist or belongs to another quiz.
	 */
	public function apply( int $quiz_id, int $activity_id, bool $update_course = false ): bool {
		$attempt = $this->attempts->get( $activity_id );

		if ( null === $attempt || (int) $attempt->post_id !== $quiz_id ) {
			return false;
		}

		$meta   = $this->attempts->get_meta( $activity_id );
		$ref_id = $this->statistic_ref_id( $quiz_id, $attempt, $meta );
		$result = $this->score( $quiz_id, $attempt, $meta, $ref_id );
		$passed = $result->passes( $this->quizzes->get_passing_percentage( $quiz_id ) );

		$this->attempts->update_meta(
			$activity_id,
			array(
				'score'          => $result->score,
				'points'         => $result->score,
				'total_points'   => $result->total_points,
				'percentage'     => $result->percentage(),
				'pass'           => $passed ? 1 : 0,
				'regraded'       => 1,
				'regraded_date'  => current_time( 'mysql' ),
				// Keep the first original score across repeated regrades.
				'original_score' => isset( $meta['original_score'] ) ? $meta['original_score'] : ( isset( $meta['score'] ) ? (int) $meta['score'] : 0 ),
			)
		);
		$this->attempts->update_status( $activity_id, $passed );

		$this->update_user_quiz_meta( $quiz_id, $attempt, $ref_id, $result, $passed );

		if ( $ref_id > 0 ) {
			$rescored = array_filter(
				$result->question_results,
				static function ( $question_result ) {
					return ScoreResult::STATUS_RESCORED === $question_result['status'];
				}
			);
			$this->attempts->update_question_statistics( $ref_id, $rescored );
		}

		if ( $update_course && $passed && function_exists( 'learndash_process_mark_complete' ) ) {
			// LearnDash re-evaluates course completion itself once the quiz step is complete.
			learndash_process_mark_complete( (int) $attempt->user_id, $quiz_id, false, (int) $attempt->course_id );
		}

		/**
		 * Fires after an attempt has been regraded and saved.
		 *
		 * @since 2.0.0
		 *
		 * @param int         $activity_id Activity ID.
		 * @param ScoreResult $result      New score.
		 * @param array       $meta        Activity meta before the update.
		 */
		do_action( 'ldar_attempt_regraded', $activity_id, $result, $meta );

		return true;
	}

	/**
	 * Recalculate and save several attempts.
	 *
	 * @param int   $quiz_id       Quiz post ID.
	 * @param int[] $activity_ids  Activity IDs.
	 * @param bool  $update_course Mark the quiz complete when it now passes.
	 * @return int Number of attempts updated.
	 */
	public function apply_many( int $quiz_id, array $activity_ids, bool $update_course = false ): int {
		$updated = 0;

		foreach ( array_unique( array_map( 'intval', $activity_ids ) ) as $activity_id ) {
			if ( $activity_id > 0 && $this->apply( $quiz_id, $activity_id, $update_course ) ) {
				++$updated;
			}
		}

		return $updated;
	}

	/**
	 * Recalculate and save one batch of attempts matching the filters.
	 *
	 * Rows are ordered by completion time and ID, and regrading never adds or
	 * removes attempts, so offset paging stays stable across batches.
	 *
	 * @param AttemptQuery $query         Filters, offset and batch size.
	 * @param bool         $update_course Mark the quiz complete when it now passes.
	 * @return array{processed: int, updated: int, total: int, next_offset: int, done: bool}
	 */
	public function apply_batch( AttemptQuery $query, bool $update_course = false ): array {
		$found = $this->attempts->find( $query );
		$ids   = array_map(
			static function ( $attempt ) {
				return (int) $attempt->activity_id;
			},
			$found['items']
		);

		$updated     = $this->apply_many( $query->quiz_id, $ids, $update_course );
		$next_offset = $query->offset + count( $ids );

		return array(
			'processed'   => count( $ids ),
			'updated'     => $updated,
			'total'       => $found['total'],
			'next_offset' => $next_offset,
			'done'        => empty( $ids ) || $next_offset >= $found['total'],
		);
	}

	/**
	 * Score an attempt against the quiz's current questions.
	 *
	 * @param int                  $quiz_id Quiz post ID.
	 * @param object               $attempt Attempt row.
	 * @param array<string, mixed> $meta    Attempt meta.
	 * @param int|null             $ref_id  Statistic reference ID, looked up when null.
	 * @return ScoreResult
	 */
	private function score( int $quiz_id, $attempt, array $meta, ?int $ref_id = null ): ScoreResult {
		$pro_quiz_id = $this->quizzes->get_pro_quiz_id( $quiz_id );
		if ( null === $ref_id ) {
			$ref_id = $this->statistic_ref_id( $quiz_id, $attempt, $meta );
		}

		return $this->calculator->calculate(
			$this->quizzes->get_questions( $pro_quiz_id ),
			$this->attempts->get_answers( $ref_id )
		);
	}

	/**
	 * Statistic reference ID of an attempt, from meta or by lookup.
	 *
	 * @param int                  $quiz_id Quiz post ID.
	 * @param object               $attempt Attempt row.
	 * @param array<string, mixed> $meta    Attempt meta.
	 * @return int
	 */
	private function statistic_ref_id( int $quiz_id, $attempt, array $meta ): int {
		if ( ! empty( $meta['statistic_ref_id'] ) ) {
			return (int) $meta['statistic_ref_id'];
		}

		return $this->attempts->find_statistic_ref_id(
			(int) $attempt->user_id,
			$this->quizzes->get_pro_quiz_id( $quiz_id ),
			(int) $attempt->activity_completed
		);
	}

	/**
	 * Stored percentage, falling back to score / points when missing.
	 *
	 * @param array<string, mixed> $meta          Attempt meta.
	 * @param int                  $current_score Stored score.
	 * @return float
	 */
	private function current_percentage( array $meta, int $current_score ): float {
		$percentage = isset( $meta['percentage'] ) ? (float) $meta['percentage'] : 0.0;
		$total      = isset( $meta['total_points'] ) ? (int) $meta['total_points'] : 0;

		if ( 0.0 === $percentage && $total > 0 ) {
			$percentage = round( ( $current_score / $total ) * 100, 2 );
		}

		return $percentage;
	}

	/**
	 * Update the user's `_sfwd-quizzes` meta for this attempt.
	 *
	 * @param int         $quiz_id Quiz post ID.
	 * @param object      $attempt Attempt row.
	 * @param int         $ref_id  Statistic reference ID.
	 * @param ScoreResult $result  New score.
	 * @param bool        $passed  Whether it now passes.
	 * @return void
	 */
	private function update_user_quiz_meta( int $quiz_id, $attempt, int $ref_id, ScoreResult $result, bool $passed ): void {
		$user_id = (int) $attempt->user_id;
		$meta    = get_user_meta( $user_id, '_sfwd-quizzes', true );

		if ( ! is_array( $meta ) ) {
			return;
		}

		$outcome = $this->meta_updater->apply(
			$meta,
			$quiz_id,
			(int) $attempt->activity_completed,
			$ref_id,
			array(
				'score'        => $result->score,
				'points'       => $result->score,
				'total_points' => $result->total_points,
				'percentage'   => $result->percentage(),
				'pass'         => $passed ? 1 : 0,
				'm_edit_by'    => get_current_user_id(),
				'm_edit_time'  => time(),
			)
		);

		if ( $outcome['updated'] ) {
			update_user_meta( $user_id, '_sfwd-quizzes', $outcome['meta'] );
		}
	}
}

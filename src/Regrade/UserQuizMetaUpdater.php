<?php
/**
 * User quiz meta updater.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Regrade;

/**
 * Updates the matching attempt inside a user's `_sfwd-quizzes` meta array.
 *
 * Pure array logic, kept apart from WordPress so the matching rules can be
 * unit tested. Matching order:
 *
 * 1. The attempt with the same statistic reference ID.
 * 2. The attempt completed within 60 seconds of the activity.
 * 3. The only attempt for this quiz, when there is exactly one.
 *
 * If none match, the meta is left untouched rather than guessing and
 * overwriting a different attempt.
 *
 * @since 2.0.0
 */
class UserQuizMetaUpdater {

	/**
	 * Allowed clock drift between the activity row and the meta entry, in seconds.
	 */
	const TIME_TOLERANCE = 60;

	/**
	 * Apply new score values to the matching attempt.
	 *
	 * @param array<int|string, mixed> $meta             The `_sfwd-quizzes` user meta.
	 * @param int                      $quiz_id          Quiz post ID.
	 * @param int                      $completed        Activity completion timestamp.
	 * @param int                      $statistic_ref_id Statistic reference ID (0 if unknown).
	 * @param array<string, mixed>     $values           Keys to write (score, percentage, ...).
	 * @return array{meta: array<int|string, mixed>, updated: bool}
	 */
	public function apply( array $meta, int $quiz_id, int $completed, int $statistic_ref_id, array $values ): array {
		$key = $this->find_attempt_key( $meta, $quiz_id, $completed, $statistic_ref_id );

		if ( null === $key ) {
			return array(
				'meta'    => $meta,
				'updated' => false,
			);
		}

		$meta[ $key ] = array_merge( $meta[ $key ], $values );

		return array(
			'meta'    => $meta,
			'updated' => true,
		);
	}

	/**
	 * Find the meta key of the attempt that matches the activity.
	 *
	 * @param array<int|string, mixed> $meta             The `_sfwd-quizzes` user meta.
	 * @param int                      $quiz_id          Quiz post ID.
	 * @param int                      $completed        Activity completion timestamp.
	 * @param int                      $statistic_ref_id Statistic reference ID.
	 * @return int|string|null
	 */
	public function find_attempt_key( array $meta, int $quiz_id, int $completed, int $statistic_ref_id ) {
		$quiz_keys = array();

		foreach ( $meta as $key => $attempt ) {
			if ( is_array( $attempt ) && isset( $attempt['quiz'] ) && (int) $attempt['quiz'] === $quiz_id ) {
				$quiz_keys[] = $key;
			}
		}

		if ( $statistic_ref_id > 0 ) {
			foreach ( $quiz_keys as $key ) {
				if ( isset( $meta[ $key ]['statistic_ref_id'] ) && (int) $meta[ $key ]['statistic_ref_id'] === $statistic_ref_id ) {
					return $key;
				}
			}
		}

		if ( $completed > 0 ) {
			foreach ( $quiz_keys as $key ) {
				if ( isset( $meta[ $key ]['time'] ) && abs( (int) $meta[ $key ]['time'] - $completed ) <= self::TIME_TOLERANCE ) {
					return $key;
				}
			}
		}

		if ( 1 === count( $quiz_keys ) ) {
			return $quiz_keys[0];
		}

		return null;
	}
}

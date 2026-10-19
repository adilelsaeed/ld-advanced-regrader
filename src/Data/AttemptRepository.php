<?php
/**
 * Attempt repository.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Data;

/**
 * Reads and writes LearnDash quiz attempts and their ProQuiz statistics.
 *
 * All filtering, counting and paging happens in SQL, so large quizzes are
 * never loaded into PHP memory in full.
 *
 * @since 2.0.0
 */
class AttemptRepository {

	/**
	 * Find attempts for a quiz, newest first.
	 *
	 * @param AttemptQuery $query Filters and paging.
	 * @return array{items: object[], total: int}
	 */
	public function find( AttemptQuery $query ): array {
		global $wpdb;

		list( $from_where, $params ) = $this->build_from_where( $query );

		// $from_where is built in build_from_where() with its own placeholders; $params holds their values.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from_where}", $params ) );

		$items = array();
		if ( $total > 0 ) {
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.activity_id, a.user_id, a.post_id, a.course_id, a.activity_status, a.activity_completed, u.display_name, u.user_email
					{$from_where}
					ORDER BY a.activity_completed DESC, a.activity_id DESC
					LIMIT %d OFFSET %d",
					array_merge( $params, array( $query->limit, $query->offset ) )
				)
			);
		}
		// phpcs:enable

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Build the shared FROM/WHERE clause and its parameters.
	 *
	 * Group membership uses the `learndash_group_users_{group_id}` user meta
	 * that LearnDash maintains for every group member.
	 *
	 * @param AttemptQuery $query Filters.
	 * @return array{0: string, 1: array<int, int|string>}
	 */
	private function build_from_where( AttemptQuery $query ): array {
		global $wpdb;

		$sql    = "FROM {$this->activity_table()} a INNER JOIN {$wpdb->users} u ON u.ID = a.user_id";
		$params = array();

		if ( $query->group_id > 0 ) {
			$sql     .= " INNER JOIN {$wpdb->usermeta} gm ON gm.user_id = a.user_id AND gm.meta_key = %s";
			$params[] = 'learndash_group_users_' . $query->group_id;
		}

		$sql     .= " WHERE a.post_id = %d AND a.activity_type = 'quiz'";
		$params[] = $query->quiz_id;

		if ( '' !== $query->search ) {
			$like     = '%' . $wpdb->esc_like( $query->search ) . '%';
			$sql     .= ' AND ( u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( $sql, $params );
	}

	/**
	 * Get one quiz attempt.
	 *
	 * @param int $activity_id Activity ID.
	 * @return object|null
	 */
	public function get( int $activity_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->activity_table()} WHERE activity_id = %d AND activity_type = 'quiz'", $activity_id ) );

		return $row ? $row : null;
	}

	/**
	 * Get all meta for an attempt.
	 *
	 * @param int $activity_id Activity ID.
	 * @return array<string, mixed>
	 */
	public function get_meta( int $activity_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT activity_meta_key, activity_meta_value FROM {$this->meta_table()} WHERE activity_id = %d", $activity_id ) );

		$meta = array();
		foreach ( (array) $rows as $row ) {
			$meta[ $row->activity_meta_key ] = maybe_unserialize( $row->activity_meta_value );
		}

		return $meta;
	}

	/**
	 * Insert or update attempt meta.
	 *
	 * @param int                  $activity_id Activity ID.
	 * @param array<string, mixed> $values      Meta key => value.
	 * @return void
	 */
	public function update_meta( int $activity_id, array $values ): void {
		global $wpdb;

		$table = $this->meta_table();

		foreach ( $values as $key => $value ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT activity_meta_id FROM {$table} WHERE activity_id = %d AND activity_meta_key = %s", $activity_id, $key ) );

			if ( $exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update(
					$table,
					array( 'activity_meta_value' => maybe_serialize( $value ) ),
					array(
						'activity_id'       => $activity_id,
						'activity_meta_key' => $key,
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$table,
					array(
						'activity_id'         => $activity_id,
						'activity_meta_key'   => $key,
						'activity_meta_value' => maybe_serialize( $value ),
					)
				);
			}
		}
	}

	/**
	 * Set the pass/fail status on the activity row.
	 *
	 * @param int  $activity_id Activity ID.
	 * @param bool $passed      Whether the attempt passes.
	 * @return void
	 */
	public function update_status( int $activity_id, bool $passed ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->activity_table(),
			array( 'activity_status' => $passed ? 1 : 0 ),
			array( 'activity_id' => $activity_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Find the ProQuiz statistic reference for an attempt without a stored one.
	 *
	 * @param int $user_id     User ID.
	 * @param int $pro_quiz_id ProQuiz ID.
	 * @param int $completed   Completion timestamp.
	 * @return int 0 when not found.
	 */
	public function find_statistic_ref_id( int $user_id, int $pro_quiz_id, int $completed ): int {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$ref_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT statistic_ref_id FROM {$this->statistic_ref_table()}
				WHERE user_id = %d AND quiz_id = %d AND ABS(create_time - %d) <= 60
				ORDER BY create_time DESC LIMIT 1",
				$user_id,
				$pro_quiz_id,
				$completed
			)
		);
		// phpcs:enable

		return (int) $ref_id;
	}

	/**
	 * Get the answers stored for an attempt.
	 *
	 * @param int $statistic_ref_id Statistic reference ID.
	 * @return array<int, array{answer: mixed, points: int, correct: bool}> Keyed by question ID.
	 */
	public function get_answers( int $statistic_ref_id ): array {
		global $wpdb;

		if ( $statistic_ref_id <= 0 ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT question_id, answer_data, points, correct_count FROM {$this->statistic_table()} WHERE statistic_ref_id = %d", $statistic_ref_id ) );

		$answers = array();
		foreach ( (array) $rows as $row ) {
			$answers[ (int) $row->question_id ] = array(
				'answer'  => $this->decode_answer( $row->answer_data ),
				'points'  => (int) round( (float) $row->points ),
				'correct' => (int) $row->correct_count > 0,
			);
		}

		return $answers;
	}

	/**
	 * Write re-scored per-question results to the statistics table.
	 *
	 * @param int                                                           $statistic_ref_id Statistic reference ID.
	 * @param array<int, array{correct: bool, points: int, status: string}> $results          Per-question results.
	 * @return void
	 */
	public function update_question_statistics( int $statistic_ref_id, array $results ): void {
		global $wpdb;

		foreach ( $results as $question_id => $result ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$this->statistic_table(),
				array(
					'correct_count'   => $result['correct'] ? 1 : 0,
					'incorrect_count' => $result['correct'] ? 0 : 1,
					'points'          => $result['points'],
				),
				array(
					'statistic_ref_id' => $statistic_ref_id,
					'question_id'      => (int) $question_id,
				),
				array( '%d', '%d', '%f' ),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * Decode a stored answer (serialized or JSON).
	 *
	 * @param string|null $raw Raw answer data.
	 * @return mixed
	 */
	private function decode_answer( $raw ) {
		$data = maybe_unserialize( $raw );

		if ( is_string( $data ) ) {
			$decoded = json_decode( $data, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				return $decoded;
			}
		}

		return $data;
	}

	/**
	 * Activity table name.
	 *
	 * @return string
	 */
	private function activity_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'learndash_user_activity';
	}

	/**
	 * Activity meta table name.
	 *
	 * @return string
	 */
	private function meta_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'learndash_user_activity_meta';
	}

	/**
	 * ProQuiz statistic table name.
	 *
	 * @return string
	 */
	private function statistic_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'learndash_pro_quiz_statistic';
	}

	/**
	 * ProQuiz statistic reference table name.
	 *
	 * @return string
	 */
	private function statistic_ref_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'learndash_pro_quiz_statistic_ref';
	}
}

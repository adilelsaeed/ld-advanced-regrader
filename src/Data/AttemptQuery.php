<?php
/**
 * Attempt query value object.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Data;

/**
 * Filters for listing quiz attempts.
 *
 * @since 2.0.0
 */
class AttemptQuery {

	/**
	 * Quiz post ID.
	 *
	 * @var int
	 */
	public $quiz_id;

	/**
	 * LearnDash group ID, 0 for all.
	 *
	 * @var int
	 */
	public $group_id;

	/**
	 * Search across display name, email and login.
	 *
	 * @var string
	 */
	public $search;

	/**
	 * Row offset.
	 *
	 * @var int
	 */
	public $offset;

	/**
	 * Row limit.
	 *
	 * @var int
	 */
	public $limit;

	/**
	 * Constructor.
	 *
	 * @param int    $quiz_id  Quiz post ID.
	 * @param int    $group_id Group ID, 0 for all.
	 * @param string $search   Search string.
	 * @param int    $offset   Row offset.
	 * @param int    $limit    Row limit.
	 */
	public function __construct( int $quiz_id, int $group_id = 0, string $search = '', int $offset = 0, int $limit = 50 ) {
		$this->quiz_id  = $quiz_id;
		$this->group_id = max( 0, $group_id );
		$this->search   = trim( $search );
		$this->offset   = max( 0, $offset );
		$this->limit    = max( 1, $limit );
	}
}

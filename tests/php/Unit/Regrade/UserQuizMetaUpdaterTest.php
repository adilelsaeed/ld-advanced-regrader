<?php
/**
 * Tests for UserQuizMetaUpdater.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Tests\Unit\Regrade;

use AdilElsaeed\LDRegrader\Regrade\UserQuizMetaUpdater;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AdilElsaeed\LDRegrader\Regrade\UserQuizMetaUpdater
 */
class UserQuizMetaUpdaterTest extends TestCase {

	/**
	 * Two attempts at quiz 9 and one at quiz 7.
	 *
	 * @return array<int, array<string, int>>
	 */
	private function meta(): array {
		return array(
			array(
				'quiz'             => 9,
				'time'             => 1000,
				'score'            => 1,
				'statistic_ref_id' => 11,
			),
			array(
				'quiz'  => 7,
				'time'  => 2000,
				'score' => 2,
			),
			array(
				'quiz'             => 9,
				'time'             => 5000,
				'score'            => 3,
				'statistic_ref_id' => 12,
			),
		);
	}

	public function test_matches_by_statistic_ref_id_first(): void {
		$outcome = ( new UserQuizMetaUpdater() )->apply( $this->meta(), 9, 9999, 12, array( 'score' => 50 ) );

		$this->assertTrue( $outcome['updated'] );
		$this->assertSame( 50, $outcome['meta'][2]['score'] );
		$this->assertSame( 1, $outcome['meta'][0]['score'], 'Other attempts are untouched.' );
	}

	public function test_falls_back_to_completion_time_within_tolerance(): void {
		$outcome = ( new UserQuizMetaUpdater() )->apply( $this->meta(), 9, 1045, 0, array( 'score' => 60 ) );

		$this->assertTrue( $outcome['updated'] );
		$this->assertSame( 60, $outcome['meta'][0]['score'] );
	}

	/**
	 * Regression: version 1 overwrote "the last attempt" when nothing matched,
	 * which could change the wrong attempt's score.
	 */
	public function test_leaves_meta_untouched_when_the_match_is_ambiguous(): void {
		$meta    = $this->meta();
		$outcome = ( new UserQuizMetaUpdater() )->apply( $meta, 9, 3000, 0, array( 'score' => 70 ) );

		$this->assertFalse( $outcome['updated'] );
		$this->assertSame( $meta, $outcome['meta'] );
	}

	public function test_uses_the_only_attempt_when_there_is_exactly_one(): void {
		$outcome = ( new UserQuizMetaUpdater() )->apply( $this->meta(), 7, 99999, 0, array( 'score' => 80 ) );

		$this->assertTrue( $outcome['updated'] );
		$this->assertSame( 80, $outcome['meta'][1]['score'] );
	}

	public function test_merges_values_and_keeps_other_keys(): void {
		$outcome = ( new UserQuizMetaUpdater() )->apply(
			$this->meta(),
			9,
			0,
			11,
			array(
				'score' => 4,
				'pass'  => 1,
			)
		);

		$this->assertSame( 1000, $outcome['meta'][0]['time'] );
		$this->assertSame( 1, $outcome['meta'][0]['pass'] );
	}

	public function test_ignores_malformed_entries(): void {
		$meta = array( 'not-an-array', array( 'no_quiz_key' => true ) );

		$this->assertNull( ( new UserQuizMetaUpdater() )->find_attempt_key( $meta, 9, 1000, 0 ) );
	}
}

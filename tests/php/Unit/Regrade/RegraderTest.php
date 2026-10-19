<?php
/**
 * Tests for Regrader.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Tests\Unit\Regrade;

use AdilElsaeed\LDRegrader\Data\AttemptQuery;
use AdilElsaeed\LDRegrader\Data\AttemptRepository;
use AdilElsaeed\LDRegrader\Data\QuizRepository;
use AdilElsaeed\LDRegrader\Regrade\Regrader;
use AdilElsaeed\LDRegrader\Regrade\UserQuizMetaUpdater;
use AdilElsaeed\LDRegrader\Scoring\Question;
use AdilElsaeed\LDRegrader\Scoring\ScoreCalculator;
use AdilElsaeed\LDRegrader\Tests\TestCase;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \AdilElsaeed\LDRegrader\Regrade\Regrader
 */
class RegraderTest extends TestCase {

	const QUIZ_ID = 42;

	/**
	 * Attempt repository mock.
	 *
	 * @var AttemptRepository&MockObject
	 */
	private $attempts;

	/**
	 * Quiz repository mock.
	 *
	 * @var QuizRepository&MockObject
	 */
	private $quizzes;

	/**
	 * Regrader under test.
	 *
	 * @var Regrader
	 */
	private $regrader;

	protected function setUp(): void {
		parent::setUp();

		$this->attempts = $this->createMock( AttemptRepository::class );
		$this->quizzes  = $this->createMock( QuizRepository::class );

		// One single-choice question worth 10 whose correct option is now index 1.
		$this->quizzes->method( 'get_pro_quiz_id' )->willReturn( 5 );
		$this->quizzes->method( 'get_passing_percentage' )->willReturn( 60.0 );
		$this->quizzes->method( 'get_questions' )->willReturn(
			array( new Question( 1, 10, 'single', array( false, true ) ) )
		);
		$this->attempts->method( 'get_answers' )->willReturn(
			array(
				1 => array(
					'answer'  => 1,
					'points'  => 0,
					'correct' => false,
				),
			)
		);

		$this->regrader = new Regrader( $this->attempts, $this->quizzes, new ScoreCalculator(), new UserQuizMetaUpdater() );

		Functions\when( 'current_time' )->justReturn( '2026-10-19 10:00:00' );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
	}

	/**
	 * Attempt row as returned by the repository.
	 *
	 * @param int $quiz_id Quiz the attempt belongs to.
	 * @return object
	 */
	private function attempt( int $quiz_id = self::QUIZ_ID ) {
		return (object) array(
			'activity_id'        => 100,
			'user_id'            => 7,
			'post_id'            => $quiz_id,
			'course_id'          => 3,
			'activity_completed' => 1000,
			'display_name'       => 'Student',
		);
	}

	public function test_refuses_an_attempt_from_another_quiz(): void {
		$this->attempts->method( 'get' )->willReturn( $this->attempt( 99 ) );
		$this->attempts->expects( $this->never() )->method( 'update_meta' );
		$this->attempts->expects( $this->never() )->method( 'update_status' );

		$this->assertFalse( $this->regrader->apply( self::QUIZ_ID, 100 ) );
	}

	public function test_refuses_a_missing_attempt(): void {
		$this->attempts->method( 'get' )->willReturn( null );

		$this->assertFalse( $this->regrader->apply( self::QUIZ_ID, 100 ) );
	}

	public function test_saves_the_score_it_recalculates_itself(): void {
		$this->attempts->method( 'get' )->willReturn( $this->attempt() );
		$this->attempts->method( 'get_meta' )->willReturn(
			array(
				'score'            => 0,
				'statistic_ref_id' => 11,
			)
		);
		Functions\when( 'get_user_meta' )->justReturn(
			array(
				array(
					'quiz'             => self::QUIZ_ID,
					'time'             => 1000,
					'statistic_ref_id' => 11,
					'score'            => 0,
				),
			)
		);

		$this->attempts->expects( $this->once() )
			->method( 'update_meta' )
			->with(
				100,
				$this->callback(
					static function ( $values ) {
						return 10 === $values['score']
							&& 10 === $values['total_points']
							&& 100.0 === $values['percentage']
							&& 1 === $values['pass']
							&& 1 === $values['regraded']
							&& 0 === $values['original_score'];
					}
				)
			);
		$this->attempts->expects( $this->once() )->method( 'update_status' )->with( 100, true );
		$this->attempts->expects( $this->once() )
			->method( 'update_question_statistics' )
			->with( 11, $this->arrayHasKey( 1 ) );

		Functions\expect( 'update_user_meta' )
			->once()
			->with( 7, '_sfwd-quizzes', \Mockery::on( static function ( $meta ) {
				return 10 === $meta[0]['score'] && 1 === $meta[0]['pass'];
			} ) );
		Actions\expectDone( 'ldar_attempt_regraded' )->once();

		$this->assertTrue( $this->regrader->apply( self::QUIZ_ID, 100 ) );
	}

	public function test_keeps_the_first_original_score_on_repeat_regrades(): void {
		$this->attempts->method( 'get' )->willReturn( $this->attempt() );
		$this->attempts->method( 'get_meta' )->willReturn(
			array(
				'score'            => 10,
				'original_score'   => 2,
				'statistic_ref_id' => 11,
			)
		);
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$this->attempts->expects( $this->once() )
			->method( 'update_meta' )
			->with(
				100,
				$this->callback(
					static function ( $values ) {
						return 2 === $values['original_score'];
					}
				)
			);

		$this->regrader->apply( self::QUIZ_ID, 100 );
	}

	/**
	 * Regression: version 1 marked the course complete even when the
	 * student failed.
	 */
	public function test_marks_the_quiz_complete_only_when_it_now_passes(): void {
		$this->quizzes = $this->createMock( QuizRepository::class );
		$this->quizzes->method( 'get_pro_quiz_id' )->willReturn( 5 );
		$this->quizzes->method( 'get_passing_percentage' )->willReturn( 60.0 );
		// The student's answer is now wrong, so the attempt fails.
		$this->quizzes->method( 'get_questions' )->willReturn(
			array( new Question( 1, 10, 'single', array( true, false ) ) )
		);
		$regrader = new Regrader( $this->attempts, $this->quizzes, new ScoreCalculator(), new UserQuizMetaUpdater() );

		$this->attempts->method( 'get' )->willReturn( $this->attempt() );
		$this->attempts->method( 'get_meta' )->willReturn( array( 'statistic_ref_id' => 11 ) );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\expect( 'learndash_process_mark_complete' )->never();

		$this->assertTrue( $regrader->apply( self::QUIZ_ID, 100, true ) );
	}

	public function test_marks_the_quiz_complete_when_requested_and_passing(): void {
		$this->attempts->method( 'get' )->willReturn( $this->attempt() );
		$this->attempts->method( 'get_meta' )->willReturn( array( 'statistic_ref_id' => 11 ) );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\expect( 'learndash_process_mark_complete' )->once()->with( 7, self::QUIZ_ID, false, 3 );

		$this->assertTrue( $this->regrader->apply( self::QUIZ_ID, 100, true ) );
	}

	public function test_preview_reports_the_difference_and_pass_change(): void {
		$this->attempts->method( 'get_meta' )->willReturn(
			array(
				'score'            => 0,
				'percentage'       => 0,
				'pass'             => 0,
				'statistic_ref_id' => 11,
			)
		);

		$row = $this->regrader->preview_row( self::QUIZ_ID, $this->attempt() );

		$this->assertSame( 0, $row['current_score'] );
		$this->assertSame( 10, $row['new_score'] );
		$this->assertSame( 10, $row['difference'] );
		$this->assertFalse( $row['current_pass'] );
		$this->assertTrue( $row['new_pass'] );
		$this->assertSame( 'Student', $row['display_name'] );
	}

	public function test_batch_reports_progress_and_completion(): void {
		$this->attempts->method( 'find' )->willReturn(
			array(
				'items' => array( $this->attempt() ),
				'total' => 51,
			)
		);
		$this->attempts->method( 'get' )->willReturn( $this->attempt() );
		$this->attempts->method( 'get_meta' )->willReturn( array( 'statistic_ref_id' => 11 ) );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$result = $this->regrader->apply_batch( new AttemptQuery( self::QUIZ_ID, 0, '', 50, 50 ) );

		$this->assertSame( 1, $result['processed'] );
		$this->assertSame( 1, $result['updated'] );
		$this->assertSame( 51, $result['next_offset'] );
		$this->assertTrue( $result['done'] );
	}
}

<?php
/**
 * Tests for ScoreCalculator and ScoreResult.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Tests\Unit\Scoring;

use AdilElsaeed\LDRegrader\Scoring\Question;
use AdilElsaeed\LDRegrader\Scoring\ScoreCalculator;
use AdilElsaeed\LDRegrader\Scoring\ScoreResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AdilElsaeed\LDRegrader\Scoring\ScoreCalculator
 * @covers \AdilElsaeed\LDRegrader\Scoring\ScoreResult
 */
class ScoreCalculatorTest extends TestCase {

	/**
	 * A stored answer row.
	 *
	 * @param mixed $answer  Answer.
	 * @param int   $points  Originally awarded points.
	 * @param bool  $correct Originally correct.
	 * @return array{answer: mixed, points: int, correct: bool}
	 */
	private function stored( $answer, int $points = 0, bool $correct = false ): array {
		return array(
			'answer'  => $answer,
			'points'  => $points,
			'correct' => $correct,
		);
	}

	public function test_rescoring_uses_current_correct_answers_and_points(): void {
		// The quiz author fixed question 1: option 1 is now correct and worth 5.
		$questions = array(
			new Question( 1, 5, 'single', array( false, true ) ),
			new Question( 2, 3, 'multiple', array( true, true ) ),
		);

		$result = ( new ScoreCalculator() )->calculate(
			$questions,
			array(
				1 => $this->stored( 1, 0, false ),
				2 => $this->stored( array( 1, 0 ), 3, true ),
			)
		);

		$this->assertSame( 5, $result->score );
		$this->assertSame( 8, $result->total_points );
		$this->assertTrue( $result->question_results[1]['correct'] );
		$this->assertFalse( $result->question_results[2]['correct'] );
		$this->assertSame( ScoreResult::STATUS_RESCORED, $result->question_results[1]['status'] );
	}

	public function test_unanswered_questions_count_towards_the_total_only(): void {
		$questions = array(
			new Question( 1, 4, 'single', array( true ) ),
			new Question( 2, 6, 'single', array( true ) ),
		);

		$result = ( new ScoreCalculator() )->calculate( $questions, array( 1 => $this->stored( 0 ) ) );

		$this->assertSame( 4, $result->score );
		$this->assertSame( 10, $result->total_points );
		$this->assertArrayNotHasKey( 2, $result->question_results );
	}

	/**
	 * Regression: unsupported types used to be scored as wrong, silently
	 * removing points the student had earned.
	 */
	public function test_unsupported_types_keep_the_original_points(): void {
		$questions = array(
			new Question( 1, 10, 'free_answer', array() ),
			new Question( 2, 5, 'sort_answer', array() ),
		);

		$result = ( new ScoreCalculator() )->calculate(
			$questions,
			array(
				1 => $this->stored( 'paris', 10, true ),
				2 => $this->stored( array( 2, 1 ), 0, false ),
			)
		);

		$this->assertSame( 10, $result->score );
		$this->assertSame( ScoreResult::STATUS_KEPT, $result->question_results[1]['status'] );
		$this->assertTrue( $result->question_results[1]['correct'] );
		$this->assertFalse( $result->question_results[2]['correct'] );
		$this->assertSame( 2, $result->kept_count() );
	}

	public function test_percentage_and_pass_threshold(): void {
		$result = new ScoreResult( 15, 22, array() );

		$this->assertSame( 68.18, $result->percentage() );
		$this->assertTrue( $result->passes( 68.18 ) );
		$this->assertFalse( $result->passes( 70 ) );
	}

	public function test_zero_total_points_gives_zero_percent(): void {
		$result = ( new ScoreCalculator() )->calculate( array(), array() );

		$this->assertSame( 0.0, $result->percentage() );
		$this->assertTrue( $result->passes( 0 ) );
	}
}

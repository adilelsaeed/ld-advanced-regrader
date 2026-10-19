<?php
/**
 * Tests for AnswerEvaluator.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Tests\Unit\Scoring;

use AdilElsaeed\LDRegrader\Scoring\AnswerEvaluator;
use AdilElsaeed\LDRegrader\Scoring\Question;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AdilElsaeed\LDRegrader\Scoring\AnswerEvaluator
 */
class AnswerEvaluatorTest extends TestCase {

	/**
	 * Evaluator under test.
	 *
	 * @var AnswerEvaluator
	 */
	private $evaluator;

	protected function setUp(): void {
		$this->evaluator = new AnswerEvaluator();
	}

	/**
	 * @dataProvider single_choice_answers
	 *
	 * @param mixed $answer   Stored answer.
	 * @param bool  $expected Whether it is correct.
	 */
	public function test_single_choice( $answer, bool $expected ): void {
		$question = new Question( 1, 5, 'single', array( false, true, false ) );

		$this->assertSame( $expected, $this->evaluator->is_correct( $question, $answer ) );
	}

	/**
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public function single_choice_answers(): array {
		return array(
			'correct index'           => array( 1, true ),
			'correct index as string' => array( '1', true ),
			'wrong index'             => array( 0, false ),
			'flags array, correct'    => array( array( 0, 1, 0 ), true ),
			'flags array, wrong'      => array( array( 1, 0, 0 ), false ),
			'flags array, none'       => array( array( 0, 0, 0 ), false ),
		);
	}

	/**
	 * Regression: with loose comparison, `null == 0` is true, so a missing
	 * answer used to count as picking the first option.
	 *
	 * @dataProvider empty_answers
	 *
	 * @param mixed $answer Empty stored answer.
	 */
	public function test_missing_answer_never_selects_the_first_option( $answer ): void {
		$question = new Question( 1, 5, 'single', array( true, false ) );

		$this->assertFalse( $this->evaluator->is_correct( $question, $answer ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public function empty_answers(): array {
		return array(
			'null'         => array( null ),
			'empty string' => array( '' ),
			'non-numeric'  => array( 'abc' ),
			'false'        => array( false ),
		);
	}

	public function test_multiple_choice_requires_an_exact_match(): void {
		$question = new Question( 1, 4, 'multiple', array( true, false, true ) );

		$this->assertTrue( $this->evaluator->is_correct( $question, array( 1, 0, 1 ) ) );
		$this->assertFalse( $this->evaluator->is_correct( $question, array( 1, 0, 0 ) ), 'Missing a correct option.' );
		$this->assertFalse( $this->evaluator->is_correct( $question, array( 1, 1, 1 ) ), 'Selecting a wrong option.' );
	}

	public function test_multiple_choice_rejects_a_bare_index(): void {
		$question = new Question( 1, 4, 'multiple', array( true, false ) );

		$this->assertFalse( $this->evaluator->is_correct( $question, 0 ) );
	}

	public function test_unsupported_types_are_reported_and_never_correct(): void {
		$question = new Question( 1, 10, 'free_answer', array( true ) );

		$this->assertFalse( $this->evaluator->supports( 'free_answer' ) );
		$this->assertTrue( $this->evaluator->supports( 'single' ) );
		$this->assertFalse( $this->evaluator->is_correct( $question, array( 1 ) ) );
	}

	public function test_question_without_options_is_never_correct(): void {
		$question = new Question( 1, 5, 'single', array() );

		$this->assertFalse( $this->evaluator->is_correct( $question, 0 ) );
	}
}

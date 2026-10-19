<?php
/**
 * Answer evaluator.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Scoring;

/**
 * Decides whether a stored student answer is correct against the question's
 * current settings.
 *
 * @since 2.0.0
 */
class AnswerEvaluator {

	/**
	 * Answer types this evaluator can re-score.
	 *
	 * @var string[]
	 */
	const SUPPORTED_TYPES = array( 'single', 'multiple' );

	/**
	 * Whether a question type can be re-scored.
	 *
	 * @param string $type ProQuiz answer type.
	 * @return bool
	 */
	public function supports( string $type ): bool {
		return in_array( $type, self::SUPPORTED_TYPES, true );
	}

	/**
	 * Whether the stored answer is fully correct for the question.
	 *
	 * Every option must match: correct options selected, incorrect ones not.
	 *
	 * @param Question $question    Question with its current correct flags.
	 * @param mixed    $user_answer Stored answer: an array of 0/1 flags keyed by
	 *                              option index, or (single choice only) the
	 *                              selected option index.
	 * @return bool
	 */
	public function is_correct( Question $question, $user_answer ): bool {
		if ( ! $this->supports( $question->type ) || empty( $question->correct_flags ) ) {
			return false;
		}

		$allow_scalar = ( 'single' === $question->type );

		foreach ( $question->correct_flags as $index => $should_be_selected ) {
			if ( (bool) $should_be_selected !== $this->is_selected( $user_answer, $index, $allow_scalar ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the option at $index was selected in the stored answer.
	 *
	 * @param mixed      $user_answer  Stored answer.
	 * @param int|string $index        Option index.
	 * @param bool       $allow_scalar Accept a bare index as the answer.
	 * @return bool
	 */
	private function is_selected( $user_answer, $index, bool $allow_scalar ): bool {
		if ( is_array( $user_answer ) ) {
			return isset( $user_answer[ $index ] ) && 1 === (int) $user_answer[ $index ];
		}

		// A missing answer must never count as picking option 0
		// (null == 0 is true with loose comparison).
		if ( ! $allow_scalar || null === $user_answer || '' === $user_answer || ! is_numeric( $user_answer ) ) {
			return false;
		}

		return (int) $user_answer === (int) $index;
	}
}

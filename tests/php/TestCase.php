<?php
/**
 * Base test case.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Sets up and tears down Brain Monkey around every test.
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * Set up Brain Monkey and stub translation functions.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Monkey\Functions\stubTranslationFunctions();
		Monkey\Functions\stubEscapeFunctions();
	}

	/**
	 * Tear down Brain Monkey (also verifies Mockery expectations).
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}

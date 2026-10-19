<?php
/**
 * Tests for RegradeController.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Tests\Unit\Rest;

use AdilElsaeed\LDRegrader\Data\QuizRepository;
use AdilElsaeed\LDRegrader\Regrade\Regrader;
use AdilElsaeed\LDRegrader\Rest\RegradeController;
use AdilElsaeed\LDRegrader\Tests\TestCase;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

/**
 * @covers \AdilElsaeed\LDRegrader\Rest\RegradeController
 */
class RegradeControllerTest extends TestCase {

	/**
	 * Regrader mock.
	 *
	 * @var Regrader&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $regrader;

	/**
	 * Controller under test.
	 *
	 * @var RegradeController
	 */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->regrader   = $this->createMock( Regrader::class );
		$this->controller = new RegradeController( $this->regrader, $this->createMock( QuizRepository::class ) );

		Functions\when( 'rest_authorization_required_code' )->justReturn( 403 );
	}

	public function test_allows_users_with_the_capability(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );

		$this->assertTrue( $this->controller->can_manage() );
	}

	public function test_rejects_users_without_the_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = $this->controller->can_manage();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ldar_forbidden', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}

	public function test_capability_can_be_filtered(): void {
		Filters\expectApplied( 'ldar_capability' )->once()->andReturn( 'group_leader' );
		Functions\expect( 'current_user_can' )->once()->with( 'group_leader' )->andReturn( true );

		$this->assertTrue( $this->controller->can_manage() );
	}

	public function test_registers_all_routes_with_a_permission_check(): void {
		$routes = array();
		Functions\when( 'register_rest_route' )->alias(
			static function ( $namespace, $route, $args ) use ( &$routes ) {
				$routes[ $route ] = $args;
			}
		);

		$this->controller->register_routes();

		$this->assertSame( array( '/options', '/attempts', '/regrade', '/regrade/batch' ), array_keys( $routes ) );
		foreach ( $routes as $args ) {
			$this->assertSame( array( $this->controller, 'can_manage' ), $args['permission_callback'] );
		}
		$this->assertSame( RegradeController::MAX_SELECTED, $routes['/regrade']['args']['activity_ids']['maxItems'] );
	}

	public function test_selected_regrade_passes_only_ids_to_the_regrader(): void {
		$this->regrader->expects( $this->once() )
			->method( 'apply_many' )
			->with( 42, array( 5, 6 ), true )
			->willReturn( 2 );

		$response = $this->controller->regrade_selected(
			new \WP_REST_Request(
				array(
					'quiz_id'       => 42,
					'activity_ids'  => array( '5', 6 ),
					'update_course' => true,
				)
			)
		);

		$this->assertSame(
			array(
				'requested' => 2,
				'updated'   => 2,
			),
			$response->get_data()
		);
	}

	public function test_batch_uses_the_filtered_batch_size(): void {
		Filters\expectApplied( 'ldar_batch_size' )->once()->andReturn( 25 );

		$this->regrader->expects( $this->once() )
			->method( 'apply_batch' )
			->with(
				$this->callback(
					static function ( $query ) {
						return 42 === $query->quiz_id && 25 === $query->limit && 100 === $query->offset;
					}
				),
				false
			)
			->willReturn( array( 'done' => false ) );

		$this->controller->regrade_batch(
			new \WP_REST_Request(
				array(
					'quiz_id'       => 42,
					'group_id'      => 0,
					'search'        => '',
					'offset'        => 100,
					'update_course' => false,
				)
			)
		);
	}
}

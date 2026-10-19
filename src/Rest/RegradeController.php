<?php
/**
 * REST controller.
 *
 * @package AdilElsaeed\LDRegrader
 */

namespace AdilElsaeed\LDRegrader\Rest;

use AdilElsaeed\LDRegrader\Data\AttemptQuery;
use AdilElsaeed\LDRegrader\Data\QuizRepository;
use AdilElsaeed\LDRegrader\Plugin;
use AdilElsaeed\LDRegrader\Regrade\Regrader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints used by the admin app, under `ld-advanced-regrader/v1`.
 *
 * - GET  /options      Quizzes and groups for the filters.
 * - GET  /attempts     Paged preview of current vs. recalculated scores.
 * - POST /regrade      Recalculate and save selected attempts.
 * - POST /regrade/batch Recalculate and save the next batch of matching attempts.
 *
 * @since 2.0.0
 */
class RegradeController {

	const REST_NAMESPACE = 'ld-advanced-regrader/v1';

	/**
	 * Maximum attempts accepted by /regrade in one request.
	 */
	const MAX_SELECTED = 100;

	/**
	 * Regrader.
	 *
	 * @var Regrader
	 */
	private $regrader;

	/**
	 * Quiz repository.
	 *
	 * @var QuizRepository
	 */
	private $quizzes;

	/**
	 * Constructor.
	 *
	 * @param Regrader       $regrader Regrader.
	 * @param QuizRepository $quizzes  Quiz repository.
	 */
	public function __construct( Regrader $regrader, QuizRepository $quizzes ) {
		$this->regrader = $regrader;
		$this->quizzes  = $quizzes;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/options',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_options' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/attempts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_attempts' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array_merge(
					$this->filter_args(),
					array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					)
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/regrade',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'regrade_selected' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'quiz_id'       => $this->quiz_id_arg(),
					'activity_ids'  => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'minItems' => 1,
						'maxItems' => self::MAX_SELECTED,
					),
					'update_course' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/regrade/batch',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'regrade_batch' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array_merge(
					$this->filter_args(),
					array(
						'offset'        => array(
							'type'    => 'integer',
							'default' => 0,
							'minimum' => 0,
						),
						'update_course' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					)
				),
			)
		);
	}

	/**
	 * Permission check shared by every route.
	 *
	 * @return bool|WP_Error
	 */
	public function can_manage() {
		if ( current_user_can( Plugin::capability() ) ) {
			return true;
		}

		return new WP_Error(
			'ldar_forbidden',
			__( 'You are not allowed to regrade quiz attempts.', 'ld-advanced-regrader' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * GET /options.
	 *
	 * @return WP_REST_Response
	 */
	public function get_options(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'quizzes' => $this->quizzes->list_quizzes(),
				'groups'  => $this->quizzes->list_groups(),
			)
		);
	}

	/**
	 * GET /attempts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_attempts( WP_REST_Request $request ): WP_REST_Response {
		$per_page = (int) $request['per_page'];
		$page     = (int) $request['page'];

		$preview = $this->regrader->preview(
			new AttemptQuery(
				(int) $request['quiz_id'],
				(int) $request['group_id'],
				(string) $request['search'],
				( $page - 1 ) * $per_page,
				$per_page
			)
		);

		$response = new WP_REST_Response( $preview );
		$response->header( 'X-WP-Total', (string) $preview['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $preview['total'] / $per_page ) );

		return $response;
	}

	/**
	 * POST /regrade.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function regrade_selected( WP_REST_Request $request ): WP_REST_Response {
		$ids     = array_map( 'intval', (array) $request['activity_ids'] );
		$updated = $this->regrader->apply_many( (int) $request['quiz_id'], $ids, (bool) $request['update_course'] );

		return new WP_REST_Response(
			array(
				'requested' => count( $ids ),
				'updated'   => $updated,
			)
		);
	}

	/**
	 * POST /regrade/batch.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function regrade_batch( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->regrader->apply_batch(
			new AttemptQuery(
				(int) $request['quiz_id'],
				(int) $request['group_id'],
				(string) $request['search'],
				(int) $request['offset'],
				Plugin::batch_size()
			),
			(bool) $request['update_course']
		);

		return new WP_REST_Response( $result );
	}

	/**
	 * Shared filter arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function filter_args(): array {
		return array(
			'quiz_id'  => $this->quiz_id_arg(),
			'group_id' => array(
				'type'    => 'integer',
				'default' => 0,
				'minimum' => 0,
			),
			'search'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Quiz ID argument: required and must be a LearnDash quiz.
	 *
	 * @return array<string, mixed>
	 */
	private function quiz_id_arg(): array {
		return array(
			'type'              => 'integer',
			'required'          => true,
			'minimum'           => 1,
			'validate_callback' => static function ( $value ) {
				return 'sfwd-quiz' === get_post_type( (int) $value );
			},
		);
	}
}

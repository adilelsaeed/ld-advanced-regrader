<?php
/**
 * Minimal stand-ins for WordPress classes the unit tests touch.
 *
 * @package AdilElsaeed\LDRegrader
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile, Squiz.Commenting

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public $headers = array();

		public function __construct( $data = null ) {
			$this->data = $data;
		}

		public function header( $key, $value ) {
			$this->headers[ $key ] = $value;
		}

		public function get_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request implements ArrayAccess {
		private $params;

		public function __construct( array $params = array() ) {
			$this->params = $params;
		}

		#[\ReturnTypeWillChange]
		public function offsetExists( $offset ) {
			return isset( $this->params[ $offset ] );
		}

		#[\ReturnTypeWillChange]
		public function offsetGet( $offset ) {
			return isset( $this->params[ $offset ] ) ? $this->params[ $offset ] : null;
		}

		#[\ReturnTypeWillChange]
		public function offsetSet( $offset, $value ) {
			$this->params[ $offset ] = $value;
		}

		#[\ReturnTypeWillChange]
		public function offsetUnset( $offset ) {
			unset( $this->params[ $offset ] );
		}
	}
}

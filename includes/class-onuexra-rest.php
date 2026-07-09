<?php
/**
 * REST API Endpoints Handler.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

namespace OnuExra;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class REST
 */
class REST {

	/**
	 * Register REST hook.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the REST API routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'onuexra/v1',
			'/rates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_rates' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'date' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_date' ),
					),
				),
			)
		);

		register_rest_route(
			'onuexra/v1',
			'/convert',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'perform_conversion' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'amount' => array(
						'required'          => true,
						'type'              => 'number',
						'sanitize_callback' => 'floatval',
					),
					'from'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_currency' ),
					),
					'to'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_currency' ),
					),
					'date'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_date' ),
					),
				),
			)
		);
	}

	/**
	 * Validate a date parameter.
	 *
	 * @param mixed            $value   Parameter value.
	 * @param \WP_REST_Request $request REST request object.
	 * @param string           $param   Parameter name.
	 * @return bool
	 */
	public static function validate_date( $value, $request, $param ) {
		if ( empty( $value ) ) {
			return true;
		}
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
	}

	/**
	 * Validate currency code.
	 *
	 * @param mixed            $value   Parameter value.
	 * @param \WP_REST_Request $request REST request object.
	 * @param string           $param   Parameter name.
	 * @return bool
	 */
	public static function validate_currency( $value, $request, $param ) {
		return strlen( $value ) === 3;
	}

	/**
	 * Handle request to get exchange rates.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_rates( $request ) {
		$date  = $request->get_param( 'date' );
		$rates = DB::get_rates( $date );

		if ( empty( $rates ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No rates available for the requested date.', 'curs-bnr-convertor-valutar' ),
				),
				404
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'date'    => $date ? $date : current_time( 'Y-m-d' ),
				'rates'   => $rates,
			),
			200
		);
	}

	/**
	 * Handle conversion request.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function perform_conversion( $request ) {
		$amount = floatval( $request->get_param( 'amount' ) );
		$from   = strtoupper( $request->get_param( 'from' ) );
		$to     = strtoupper( $request->get_param( 'to' ) );
		$date   = $request->get_param( 'date' );

		if ( $amount < 0 ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Amount must be non-negative.', 'curs-bnr-convertor-valutar' ),
				),
				400
			);
		}

		$result = Converter::convert( $amount, $from, $to, $date );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'amount'  => $amount,
				'from'    => $from,
				'to'      => $to,
				'date'    => $date ? $date : current_time( 'Y-m-d' ),
				'result'  => round( $result, 6 ),
			),
			200
		);
	}
}

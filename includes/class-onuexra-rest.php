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

		register_rest_route(
			'onuexra/v1',
			'/chart-data',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_chart_data' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'currency'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_currency' ),
					),
					'period'     => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '1luna',
					),
					'start_date' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_date' ),
					),
					'end_date'   => array(
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

	/**
	 * Handle request to get chart historical data.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_chart_data( $request ) {
		$currency = strtoupper( sanitize_text_field( $request->get_param( 'currency' ) ) );
		if ( 'RON' === $currency ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'RON is the base pivot currency; cannot chart RON against itself.', 'curs-bnr-convertor-valutar' ),
				),
				400
			);
		}

		$period     = DB::normalize_period( $request->get_param( 'period' ) );
		$end_date   = $request->get_param( 'end_date' ) ? sanitize_text_field( $request->get_param( 'end_date' ) ) : current_time( 'Y-m-d' );
		$start_date = $request->get_param( 'start_date' ) ? sanitize_text_field( $request->get_param( 'start_date' ) ) : DB::calculate_start_date( $period, $end_date );

		// Auto-heal: If database has no rates for current year, attempt sync.
		$current_year = intval( gmdate( 'Y' ) );
		if ( ! DB::has_historical_data_for_year( $current_year ) ) {
			$lock = get_transient( 'onuexra_syncing_current_year' );
			if ( false === $lock ) {
				set_transient( 'onuexra_syncing_current_year', 1, 60 );
				Fetcher::fetch_daily();
			}
		}

		$rates = DB::get_historical_rates( $currency, $start_date, $end_date );

		if ( empty( $rates ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No exchange rates available for the requested currency and period.', 'curs-bnr-convertor-valutar' ),
				),
				404
			);
		}

		$multiplier = isset( $rates[0]['multiplier'] ) ? intval( $rates[0]['multiplier'] ) : 1;
		$label      = $currency;
		if ( $multiplier > 1 ) {
			$label = $multiplier . ' ' . $currency;
		}

		$all_rates    = array_column( $rates, 'rate' );
		$first_point  = reset( $rates );
		$last_point   = end( $rates );
		$start_rate   = floatval( $first_point['rate'] );
		$current_rate = floatval( $last_point['rate'] );

		$diff     = $current_rate - $start_rate;
		$diff_pct = $start_rate > 0 ? ( $diff / $start_rate ) * 100 : 0;
		$min_rate = min( $all_rates );
		$max_rate = max( $all_rates );
		$avg_rate = count( $all_rates ) > 0 ? ( array_sum( $all_rates ) / count( $all_rates ) ) : $current_rate;

		$sign = $diff > 0 ? '+' : '';

		$points = array();
		foreach ( $rates as $row ) {
			$points[] = array(
				'date'           => $row['date'],
				'rate'           => $row['rate'],
				'formatted_rate' => Shortcodes::format_rate( $row['rate'] ),
				'formatted_date' => date_i18n( 'j F Y', strtotime( $row['date'] ) ),
				'short_date'     => date_i18n( 'j M', strtotime( $row['date'] ) ),
			);
		}

		return new \WP_REST_Response(
			array(
				'success'             => true,
				'currency'            => $currency,
				'label'               => $label,
				'multiplier'          => $multiplier,
				'period'              => $period,
				'start_date'          => $start_date,
				'end_date'            => $end_date,
				'count'               => count( $rates ),
				'current_rate'        => $current_rate,
				'start_rate'          => $start_rate,
				'min_rate'            => $min_rate,
				'max_rate'            => $max_rate,
				'avg_rate'            => round( $avg_rate, 4 ),
				'diff'                => $diff,
				'diff_pct'            => round( $diff_pct, 2 ),
				'is_positive'         => $diff >= 0,
				'formatted_current'   => Shortcodes::format_rate( $current_rate ),
				'formatted_min'       => Shortcodes::format_rate( $min_rate ),
				'formatted_max'       => Shortcodes::format_rate( $max_rate ),
				'formatted_avg'       => Shortcodes::format_rate( $avg_rate ),
				'formatted_diff'      => $sign . Shortcodes::format_rate( $diff ),
				'formatted_diff_pct'  => $sign . number_format( $diff_pct, 2, ',', '.' ) . '%',
				'points'              => $points,
			),
			200
		);
	}
}

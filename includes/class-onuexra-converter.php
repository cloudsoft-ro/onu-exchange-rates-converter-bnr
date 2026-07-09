<?php
/**
 * Currency Converter Engine.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

namespace OnuExra;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Converter
 */
class Converter {

	/**
	 * Initialize Converter.
	 */
	public static function init() {
		// Used for hooking if needed.
	}

	/**
	 * Convert an amount from one currency to another using RON as the pivot currency.
	 *
	 * @param float       $amount        Amount to convert.
	 * @param string      $from_currency Source 3-letter currency code (e.g. 'EUR').
	 * @param string      $to_currency   Target 3-letter currency code (e.g. 'USD').
	 * @param string|null $date          Target date in YYYY-MM-DD format (falls back to latest if null).
	 * @return float|\WP_Error Converted amount or WP_Error on failure.
	 */
	public static function convert( $amount, $from_currency, $to_currency, $date = null ) {
		$amount        = floatval( $amount );
		$from_currency = strtoupper( sanitize_text_field( $from_currency ) );
		$to_currency   = strtoupper( sanitize_text_field( $to_currency ) );

		if ( $amount < 0 ) {
			return new \WP_Error( 'invalid_amount', __( 'Amount must be a positive number.', 'curs-bnr-convertor-valutar' ) );
		}

		if ( $from_currency === $to_currency ) {
			return $amount;
		}

		// Retrieve rates.
		$from_rate_info = self::get_cached_rate( $from_currency, $date );
		$to_rate_info   = self::get_cached_rate( $to_currency, $date );

		if ( ! $from_rate_info ) {
			return new \WP_Error(
				'rate_not_found',
				/* translators: %s: currency code */
				sprintf( __( 'Exchange rate for %s was not found.', 'curs-bnr-convertor-valutar' ), $from_currency )
			);
		}

		if ( ! $to_rate_info ) {
			return new \WP_Error(
				'rate_not_found',
				/* translators: %s: currency code */
				sprintf( __( 'Exchange rate for %s was not found.', 'curs-bnr-convertor-valutar' ), $to_currency )
			);
		}

		// Math: 1 unit of foreign currency = (rate / multiplier) RON.
		$from_unit_val_ron = $from_rate_info['rate'] / $from_rate_info['multiplier'];
		$to_unit_val_ron   = $to_rate_info['rate'] / $to_rate_info['multiplier'];

		if ( $to_unit_val_ron <= 0 ) {
			return new \WP_Error( 'invalid_rate', __( 'Target rate value is invalid.', 'curs-bnr-convertor-valutar' ) );
		}

		// Convert to RON, then from RON to target.
		$value_in_ron     = $amount * $from_unit_val_ron;
		$converted_amount = $value_in_ron / $to_unit_val_ron;

		return $converted_amount;
	}

	/**
	 * Retrieve a rate using WP Object Cache.
	 *
	 * @param string      $currency Currency code.
	 * @param string|null $date     Date.
	 * @return array|null
	 */
	public static function get_cached_rate( $currency, $date = null ) {
		$currency = strtoupper( $currency );
		if ( 'RON' === $currency ) {
			return array(
				'rate'       => 1.0,
				'multiplier' => 1,
			);
		}

		// Prepare cache key.
		$cache_key = 'onuexra_rate_' . $currency . '_' . ( $date ? $date : 'latest' );
		$cached    = wp_cache_get( $cache_key, 'onuexra' );

		if ( false !== $cached ) {
			return $cached;
		}

		// Fetch from database.
		$rate_info = DB::get_rate( $currency, $date );

		if ( $rate_info ) {
			wp_cache_set( $cache_key, $rate_info, 'onuexra', HOUR_IN_SECONDS );
		}

		return $rate_info;
	}

	/**
	 * Helper function for global namespace calls.
	 */
	public static function register_global_helpers() {
		// Define the wrapper function onuexra_convert in global namespace if not defined.
		if ( ! function_exists( 'onuexra_convert' ) ) {
			/**
			 * Global wrapper for currency conversion.
			 */
			function onuexra_convert( $amount, $from_currency, $to_currency, $date = null ) {
				return \OnuExra\Converter::convert( $amount, $from_currency, $to_currency, $date );
			}
		}
	}
}

// Register helper wrapper.
Converter::register_global_helpers();

<?php
/**
 * Database Handler Class.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

namespace OnuExra;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DB
 */
class DB {

	/**
	 * Table name without prefix.
	 */
	const TABLE_NAME = 'onuexra_exchange_rates';

	/**
	 * Initialize DB.
	 */
	public static function init() {
		// Can be used for hooks if needed.
	}

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create or update the custom table.
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rate_date DATE NOT NULL,
			currency_code VARCHAR(3) NOT NULL,
			rate DECIMAL(15,6) NOT NULL,
			multiplier SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY rate_date_currency (rate_date, currency_code),
			KEY currency_code (currency_code),
			KEY rate_date (rate_date)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Save current DB version.
		update_option( 'onuexra_db_version', ONUEXRA_DB_VERSION );
	}

	/**
	 * Save exchange rates to the database.
	 *
	 * @param string $date  Date in YYYY-MM-DD format.
	 * @param array  $rates Array of currency rates, e.g., [ 'EUR' => [ 'rate' => 4.9210, 'multiplier' => 1 ] ].
	 * @return bool True if at least one rate was inserted or updated, false otherwise.
	 */
	public static function save_rates( $date, array $rates ) {
		global $wpdb;

		if ( empty( $rates ) || ! $date ) {
			return false;
		}

		$table_name = esc_sql( self::get_table_name() );
		$success    = false;

		foreach ( $rates as $currency => $rate_info ) {
			$rate       = floatval( $rate_info['rate'] );
			$multiplier = isset( $rate_info['multiplier'] ) ? intval( $rate_info['multiplier'] ) : 1;
			if ( $multiplier <= 0 ) {
				$multiplier = 1;
			}

			// Format currency to uppercase.
			$currency = strtoupper( sanitize_text_field( $currency ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->replace(
				$table_name,
				array(
					'rate_date'     => $date,
					'currency_code' => $currency,
					'rate'          => $rate,
					'multiplier'    => $multiplier,
					'created_at'    => current_time( 'mysql' ),
				),
				array(
					'%s',
					'%s',
					'%f',
					'%d',
					'%s',
				)
			);

			if ( false !== $result ) {
				$success = true;
			}
		}

		return $success;
	}

	/**
	 * Get rates for a specific date.
	 *
	 * @param string|null $date Date in YYYY-MM-DD format, or null for latest.
	 * @return array
	 */
	public static function get_rates( $date = null ) {
		global $wpdb;

		$table_name = esc_sql( self::get_table_name() );

		if ( ! $date ) {
			// Try cache first for the latest date.
			$latest_date = wp_cache_get( 'onuexra_latest_date', 'onuexra' );
			if ( false === $latest_date ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$latest_date = $wpdb->get_var( "SELECT MAX(rate_date) FROM $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				wp_cache_set( 'onuexra_latest_date', $latest_date ? $latest_date : '', 'onuexra', HOUR_IN_SECONDS );
			}
			if ( ! $latest_date ) {
				return array();
			}
			$date = $latest_date;
		}

		$cache_key = 'onuexra_rates_' . $date;
		$rates     = wp_cache_get( $cache_key, 'onuexra' );
		if ( false !== $rates ) {
			return $rates;
		}

		$query = $wpdb->prepare( "SELECT currency_code, rate, multiplier FROM $table_name WHERE rate_date = %s", $date ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rates = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$rates[ $row['currency_code'] ] = array(
					'rate'       => floatval( $row['rate'] ),
					'multiplier' => intval( $row['multiplier'] ),
				);
			}
		}

		wp_cache_set( $cache_key, $rates, 'onuexra', HOUR_IN_SECONDS );

		return $rates;
	}

	/**
	 * Get a rate for a specific currency at a given date (or fallback to latest before/on that date).
	 *
	 * @param string      $currency_code 3-letter currency code.
	 * @param string|null $date          Date in YYYY-MM-DD format, or null for today.
	 * @return array|null Array with rate and multiplier, or null.
	 */
	public static function get_rate( $currency_code, $date = null ) {
		global $wpdb;

		$table_name    = esc_sql( self::get_table_name() );
		$currency_code = strtoupper( sanitize_text_field( $currency_code ) );

		if ( 'RON' === $currency_code ) {
			return array(
				'rate'       => 1.0,
				'multiplier' => 1,
			);
		}

		if ( ! $date ) {
			$date = current_time( 'Y-m-d' );
		}

		$cache_key = 'onuexra_rate_' . $currency_code . '_' . $date;
		$cached    = wp_cache_get( $cache_key, 'onuexra' );
		if ( false !== $cached ) {
			return $cached;
		}

		// Query latest rate on or before the requested date.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT rate, multiplier, rate_date FROM $table_name 
			WHERE currency_code = %s AND rate_date <= %s 
			ORDER BY rate_date DESC LIMIT 1",
			$currency_code,
			$date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$result = null;
		if ( $row ) {
			$result = array(
				'rate'       => floatval( $row['rate'] ),
				'multiplier' => intval( $row['multiplier'] ),
				'date'       => $row['rate_date'],
			);
		}

		wp_cache_set( $cache_key, $result, 'onuexra', HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Get the rate for a currency immediately preceding the specified date.
	 *
	 * @param string $currency_code 3-letter currency code.
	 * @param string $current_date  The current rate date.
	 * @return array|null Array with rate and multiplier, or null.
	 */
	public static function get_previous_rate( $currency_code, $current_date ) {
		global $wpdb;

		$table_name    = esc_sql( self::get_table_name() );
		$currency_code = strtoupper( sanitize_text_field( $currency_code ) );

		if ( 'RON' === $currency_code ) {
			return array(
				'rate'       => 1.0,
				'multiplier' => 1,
			);
		}

		$cache_key = 'onuexra_prev_rate_' . $currency_code . '_' . $current_date;
		$cached    = wp_cache_get( $cache_key, 'onuexra' );
		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT rate, multiplier, rate_date FROM $table_name 
			WHERE currency_code = %s AND rate_date < %s 
			ORDER BY rate_date DESC LIMIT 1",
			$currency_code,
			$current_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$result = null;
		if ( $row ) {
			$result = array(
				'rate'       => floatval( $row['rate'] ),
				'multiplier' => intval( $row['multiplier'] ),
				'date'       => $row['rate_date'],
			);
		}

		wp_cache_set( $cache_key, $result, 'onuexra', HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Get all active currency codes stored in DB.
	 *
	 * @return array
	 */
	public static function get_active_currencies() {
		global $wpdb;

		$cached = wp_cache_get( 'onuexra_active_currencies', 'onuexra' );
		if ( false !== $cached ) {
			return $cached;
		}

		$table_name  = esc_sql( self::get_table_name() );
		$query       = "SELECT DISTINCT currency_code FROM $table_name ORDER BY currency_code ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$currencies  = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		wp_cache_set( 'onuexra_active_currencies', $currencies, 'onuexra', HOUR_IN_SECONDS );

		return $currencies;
	}

	/**
	 * Clean up old exchange rates data.
	 *
	 * @param int $keep_days Number of days to keep.
	 * @return int Number of rows deleted.
	 */
	public static function clean_old_data( $keep_days ) {
		global $wpdb;

		$keep_days = intval( $keep_days );
		if ( $keep_days <= 0 ) {
			return 0;
		}

		$table_name = esc_sql( self::get_table_name() );
		$date_limit = gmdate( 'Y-m-d', strtotime( "-$keep_days days" ) );

		$query = $wpdb->prepare( "DELETE FROM $table_name WHERE rate_date < %s", $date_limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Normalize period string.
	 *
	 * @param string $period Period key (e.g. '7zile', '1luna', '3luni', '1an', '5ani').
	 * @return string Normalized period key.
	 */
	public static function normalize_period( $period ) {
		$period = strtolower( trim( (string) $period ) );
		switch ( $period ) {
			case '7zile':
			case '7z':
			case '7days':
			case '7d':
			case '7_zile':
				return '7zile';

			case '1luna':
			case '1l':
			case '1month':
			case '1m':
			case '1_luna':
				return '1luna';

			case '3luni':
			case '3l':
			case '3months':
			case '3m':
			case '3_luni':
				return '3luni';

			case '1an':
			case '1a':
			case '1year':
			case '1y':
			case '1_an':
				return '1an';

			case '5ani':
			case '5a':
			case '5years':
			case '5y':
			case '5_ani':
				return '5ani';

			default:
				return '1luna';
		}
	}

	/**
	 * Calculate start date for a specific period.
	 *
	 * @param string      $period   Normalized period key ('7zile', '1luna', '3luni', '1an', '5ani').
	 * @param string|null $end_date Target end date in YYYY-MM-DD (defaults to today).
	 * @return string Start date in YYYY-MM-DD.
	 */
	public static function calculate_start_date( $period, $end_date = null ) {
		$period = self::normalize_period( $period );
		$end_ts = $end_date ? strtotime( $end_date ) : current_time( 'timestamp' );

		switch ( $period ) {
			case '7zile':
				return gmdate( 'Y-m-d', strtotime( '-7 days', $end_ts ) );
			case '1luna':
				return gmdate( 'Y-m-d', strtotime( '-1 month', $end_ts ) );
			case '3luni':
				return gmdate( 'Y-m-d', strtotime( '-3 months', $end_ts ) );
			case '1an':
				return gmdate( 'Y-m-d', strtotime( '-1 year', $end_ts ) );
			case '5ani':
				return gmdate( 'Y-m-d', strtotime( '-5 years', $end_ts ) );
			default:
				return gmdate( 'Y-m-d', strtotime( '-1 month', $end_ts ) );
		}
	}

	/**
	 * Get historical exchange rates for a currency over a date range.
	 *
	 * @param string      $currency_code 3-letter currency code (e.g. 'EUR').
	 * @param string      $start_date    Start date in YYYY-MM-DD format.
	 * @param string|null $end_date      End date in YYYY-MM-DD format (defaults to current date).
	 * @return array Array of rate objects [ 'date' => 'YYYY-MM-DD', 'rate' => float, 'multiplier' => int, 'raw_rate' => float ].
	 */
	public static function get_historical_rates( $currency_code, $start_date, $end_date = null ) {
		global $wpdb;

		$table_name    = esc_sql( self::get_table_name() );
		$currency_code = strtoupper( sanitize_text_field( $currency_code ) );

		if ( ! $end_date ) {
			$end_date = current_time( 'Y-m-d' );
		}

		$cache_key = 'onuexra_hist_' . $currency_code . '_' . $start_date . '_' . $end_date;
		$cached    = wp_cache_get( $cache_key, 'onuexra' );
		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT rate_date, rate, multiplier FROM $table_name 
			WHERE currency_code = %s AND rate_date >= %s AND rate_date <= %s 
			ORDER BY rate_date ASC",
			$currency_code,
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$data = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$raw_rate   = floatval( $row['rate'] );
				$multiplier = intval( $row['multiplier'] );
				if ( $multiplier <= 0 ) {
					$multiplier = 1;
				}

				$data[] = array(
					'date'       => $row['rate_date'],
					'rate'       => $raw_rate,
					'multiplier' => $multiplier,
				);
			}
		}

		wp_cache_set( $cache_key, $data, 'onuexra', HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Check if any exchange rates exist for a given year.
	 *
	 * @param int $year Year.
	 * @return bool True if records exist for that year, false otherwise.
	 */
	public static function has_historical_data_for_year( $year ) {
		global $wpdb;

		$year       = intval( $year );
		$table_name = esc_sql( self::get_table_name() );
		$start_date = "$year-01-01";
		$end_date   = "$year-12-31";

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT 1 FROM $table_name WHERE rate_date >= %s AND rate_date <= %s LIMIT 1",
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$exists = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return ! empty( $exists );
	}
}

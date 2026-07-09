<?php
/**
 * Cron Operations Class.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

namespace OnuExra;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cron
 */
class Cron {

	/**
	 * Hook initialization.
	 */
	public static function init() {
		add_action( 'onuexra_daily_fetch', array( __CLASS__, 'do_daily_fetch' ) );
	}

	/**
	 * Run the daily fetch process and perform history cleanup if needed.
	 */
	public static function do_daily_fetch() {
		// Run daily rate fetch.
		Fetcher::fetch_daily();

		// Cleanup history if option is configured.
		$keep_days = intval( get_option( 'onuexra_keep_history_days', 0 ) );
		if ( $keep_days > 0 ) {
			DB::clean_old_data( $keep_days );
		}
	}

	/**
	 * Calculate the UTC timestamp for the next occurrence of a time string.
	 *
	 * @param string $time_string Time formatted as 'HH:MM'.
	 * @return int UTC timestamp.
	 */
	public static function get_next_occurrence_timestamp( $time_string ) {
		// Get timezone from WordPress.
		$timezone = wp_timezone();
		$now      = new \DateTime( 'now', $timezone );

		$time_parts = explode( ':', $time_string );
		$hour       = isset( $time_parts[0] ) ? intval( $time_parts[0] ) : 13;
		$minute     = isset( $time_parts[1] ) ? intval( $time_parts[1] ) : 15;

		$scheduled_time = new \DateTime( 'now', $timezone );
		$scheduled_time->setTime( $hour, $minute, 0 );

		// If the scheduled time for today has already passed, schedule for tomorrow.
		if ( $scheduled_time <= $now ) {
			$scheduled_time->modify( '+1 day' );
		}

		return $scheduled_time->getTimestamp();
	}

	/**
	 * Schedule the daily fetch cron event.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'onuexra_daily_fetch' ) ) {
			$time_string = get_option( 'onuexra_fetch_time', '13:15' );
			$timestamp   = self::get_next_occurrence_timestamp( $time_string );
			wp_schedule_event( $timestamp, 'daily', 'onuexra_daily_fetch' );
		}
	}

	/**
	 * Unschedule the daily fetch cron event.
	 */
	public static function clear() {
		$timestamp = wp_next_scheduled( 'onuexra_daily_fetch' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'onuexra_daily_fetch' );
		}
	}

	/**
	 * Re-schedule the daily fetch cron event with a new time configuration.
	 */
	public static function reschedule() {
		self::clear();
		$time_string = get_option( 'onuexra_fetch_time', '13:15' );
		$timestamp   = self::get_next_occurrence_timestamp( $time_string );
		wp_schedule_event( $timestamp, 'daily', 'onuexra_daily_fetch' );
	}
}

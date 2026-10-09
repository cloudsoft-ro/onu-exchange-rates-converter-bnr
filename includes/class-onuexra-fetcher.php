<?php
/**
 * XML Fetcher and Parser Class for BNR.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

namespace OnuExra;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fetcher
 */
class Fetcher {

	/**
	 * Daily rates XML URL.
	 */
	const DAILY_URL = 'https://www.bnr.ro/nbrfxrates.xml';

	/**
	 * Annual historical XML URL template.
	 */
	const ANNUAL_URL_TEMPLATE = 'https://bnr.ro/files/xml/years/nbrfxrates%d.xml';

	/**
	 * Initialize Fetcher.
	 */
	public static function init() {
		// Used for hooking or initial steps if needed.
	}

	/**
	 * Securely parse XML string, avoiding XXE vulnerabilities.
	 *
	 * @param string $xml_string XML content.
	 * @return \SimpleXMLElement|\WP_Error
	 */
	public static function parse_xml( $xml_string ) {
		if ( empty( $xml_string ) ) {
			return new \WP_Error( 'xml_empty', __( 'XML content is empty.', 'curs-bnr-convertor-valutar' ) );
		}

		// Prevent XXE on older PHP versions.
		$disable_entity_loader = false;
		if ( PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			// phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
			$disable_entity_loader = libxml_disable_entity_loader( true );
		}

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_string, 'SimpleXMLElement', LIBXML_NONET );

		// Re-enable entity loader if disabled previously.
		if ( PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			// phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
			libxml_disable_entity_loader( $disable_entity_loader );
		}

		if ( ! $xml ) {
			$errors    = libxml_get_errors();
			$err_msg   = '';
			foreach ( $errors as $error ) {
				$err_msg .= sprintf( 'Line %d: %s; ', $error->line, trim( $error->message ) );
			}
			libxml_clear_errors();
			/* translators: %s: error message details */
			return new \WP_Error( 'xml_parse_failed', sprintf( __( 'Failed to parse BNR XML: %s', 'curs-bnr-convertor-valutar' ), $err_msg ) );
		}

		return $xml;
	}

	/**
	 * Process XML and save rates to the database.
	 *
	 * @param \SimpleXMLElement $xml Parsed XML Element.
	 * @return array| \WP_Error Stats array of imported dates and currencies, or WP_Error.
	 */
	public static function save_parsed_xml( $xml ) {
		// Identify the namespace URL.
		$namespaces = $xml->getNamespaces( true );
		$ns_url     = isset( $namespaces[''] ) ? $namespaces[''] : 'http://www.bnr.ro/xsd';

		// Find Cube elements.
		$xml->registerXPathNamespace( 'ns', $ns_url );
		$cubes = $xml->xpath( '//ns:Cube' );

		// Fallback if namespaces are absent or modified.
		if ( empty( $cubes ) ) {
			$cubes = $xml->xpath( '//Cube' );
		}

		if ( empty( $cubes ) ) {
			return new \WP_Error( 'xml_no_cubes', __( 'No Cube elements found in the BNR XML.', 'curs-bnr-convertor-valutar' ) );
		}

		$stats = array(
			'dates_processed' => 0,
			'rates_saved'     => 0,
		);

		foreach ( $cubes as $cube ) {
			$date = (string) $cube['date'];
			if ( ! $date ) {
				continue;
			}

			// Validate date format YYYY-MM-DD.
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				continue;
			}

			// Get children of the Cube.
			$rates_xml = $cube->children( $ns_url );
			if ( count( $rates_xml ) === 0 ) {
				$rates_xml = $cube->children();
			}

			$rates_to_save = array();

			foreach ( $rates_xml as $child ) {
				if ( 'Rate' === $child->getName() ) {
					// Use attributes() to query namespace-free attributes on namespaced element.
					$currency = isset( $child->attributes()->currency ) ? (string) $child->attributes()->currency : '';
					if ( strlen( $currency ) !== 3 ) {
						continue;
					}

					$multiplier = isset( $child->attributes()->multiplier ) ? intval( $child->attributes()->multiplier ) : 1;
					if ( $multiplier <= 0 ) {
						$multiplier = 1;
					}

					$rate = floatval( (string) $child );
					if ( $rate <= 0 ) {
						continue;
					}

					$rates_to_save[ $currency ] = array(
						'rate'       => $rate,
						'multiplier' => $multiplier,
					);
				}
			}

			if ( ! empty( $rates_to_save ) ) {
				$saved = DB::save_rates( $date, $rates_to_save );
				if ( $saved ) {
					$stats['dates_processed']++;
					$stats['rates_saved'] += count( $rates_to_save );
				}
			}
		}

		return $stats;
	}

	/**
	 * Fetch exchange rates from a URL.
	 *
	 * Performs an HTTP GET request to the given URL and validates the response
	 * before attempting to parse it as XML. Specifically, it checks:
	 *  - HTTP status code is 200.
	 *  - Response body is not empty.
	 *  - Content-Type header contains 'xml' (guards against HTML error pages
	 *    returned by WAFs, CDNs, or BNR maintenance pages with a 200 status).
	 *  - Response body does not start with an HTML tag (secondary guard for
	 *    servers that omit or misreport their Content-Type).
	 *
	 * @param string $url Target XML URL.
	 * @return array|\WP_Error Stats array on success, or WP_Error on failure.
	 */
	public static function fetch_url( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; Onu Exchange Rates and Converter for BNR',
			) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			/* translators: %d: HTTP status code */
			return new \WP_Error( 'http_error_code', sprintf( __( 'BNR server returned HTTP status code %d', 'curs-bnr-convertor-valutar' ), $status_code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new \WP_Error( 'http_empty_body', __( 'BNR server returned an empty body.', 'curs-bnr-convertor-valutar' ) );
		}

		// Verify the response is XML and not an HTML error page (e.g. WAF, maintenance page).
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( ! empty( $content_type ) && false === strpos( $content_type, 'xml' ) && false === strpos( $content_type, 'octet-stream' ) ) {
			return new \WP_Error(
				'http_not_xml',
				/* translators: %s: Content-Type value returned by the server */
				sprintf( __( 'BNR server did not return XML (Content-Type: %s). The server may be down or returning an error page.', 'curs-bnr-convertor-valutar' ), $content_type )
			);
		}

		// Guard against HTML error pages that slip through with an XML content-type or no content-type.
		$trimmed_body = ltrim( $body );
		if ( '<' !== $trimmed_body[0] || ( str_starts_with( $trimmed_body, '<!DOCTYPE' ) || str_starts_with( $trimmed_body, '<html' ) || str_starts_with( $trimmed_body, '<HTML' ) ) ) {
			return new \WP_Error( 'http_html_response', __( 'BNR server returned an HTML page instead of XML. The server may be down or returning an error page.', 'curs-bnr-convertor-valutar' ) );
		}

		$parsed_xml = self::parse_xml( $body );
		if ( is_wp_error( $parsed_xml ) ) {
			return $parsed_xml;
		}

		return self::save_parsed_xml( $parsed_xml );
	}

	/**
	 * Fetch and sync the daily exchange rates.
	 *
	 * @return array|\WP_Error
	 */
	public static function fetch_daily() {
		$result = self::fetch_url( self::DAILY_URL );

		if ( is_wp_error( $result ) ) {
			self::add_log( 'error', current_time( 'Y-m-d' ), $result->get_error_message() );
		} else {
			/* translators: 1: number of dates, 2: number of rates */
			$msg = sprintf( __( 'Successfully synchronized daily rates. Processed %1$d date(s), saved %2$d rate(s).', 'curs-bnr-convertor-valutar' ), $result['dates_processed'], $result['rates_saved'] );
			self::add_log( 'success', current_time( 'Y-m-d' ), $msg );

			// Purge the Object Cache so updated values are fetched from DB immediately.
			wp_cache_delete( 'onuexra_today_rates', 'onuexra' );
		}

		return $result;
	}

	/**
	 * Fetch and sync annual rates for a specific year.
	 *
	 * @param int $year Year to sync (e.g. 2026).
	 * @return array|\WP_Error
	 */
	public static function fetch_year( $year ) {
		$year = intval( $year );
		if ( $year < 2005 || $year > intval( gmdate( 'Y' ) ) ) {
			return new \WP_Error( 'invalid_year', __( 'Invalid year specified for sync.', 'curs-bnr-convertor-valutar' ) );
		}

		$url    = sprintf( self::ANNUAL_URL_TEMPLATE, $year );
		$result = self::fetch_url( $url );

		if ( is_wp_error( $result ) ) {
			/* translators: 1: year, 2: error message */
			self::add_log( 'error', "$year-12-31", sprintf( __( 'Error syncing historical year %1$d: %2$s', 'curs-bnr-convertor-valutar' ), $year, $result->get_error_message() ) );
		} else {
			/* translators: 1: year, 2: number of dates, 3: number of rates */
			$msg = sprintf( __( 'Successfully synchronized historical rates for year %1$d. Processed %2$d date(s), saved %3$d rate(s).', 'curs-bnr-convertor-valutar' ), $year, $result['dates_processed'], $result['rates_saved'] );
			self::add_log( 'success', "$year-12-31", $msg );
		}

		return $result;
	}

	/**
	 * Add an execution log entry and maintain a maximum count of 10 entries.
	 *
	 * @param string $status  'success' or 'error'.
	 * @param string $date    Target date context of the fetch.
	 * @param string $message Action or error message.
	 */
	public static function add_log( $status, $date, $message ) {
		$logs = get_option( 'onuexra_fetch_logs', array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		array_unshift(
			$logs,
			array(
				'time'    => current_time( 'mysql' ),
				'status'  => sanitize_text_field( $status ),
				'date'    => sanitize_text_field( $date ),
				'message' => sanitize_text_field( $message ),
			)
		);

		if ( count( $logs ) > 10 ) {
			$logs = array_slice( $logs, 0, 10 );
		}

		update_option( 'onuexra_fetch_logs', $logs );
	}
}

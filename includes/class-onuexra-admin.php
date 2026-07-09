<?php
/**
 * Administration Settings Panel Class.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

namespace OnuExra;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 */
class Admin {

	/**
	 * Initialize Admin.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Add Settings Page.
	 */
	public static function add_settings_page() {
		add_options_page(
			__( 'BNR Exchange Rates Settings', 'curs-bnr-convertor-valutar' ),
			__( 'BNR Exchange Rates', 'curs-bnr-convertor-valutar' ),
			'manage_options',
			'onuexra-settings',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Add custom plugin row meta links (Support).
	 *
	 * @param array  $links Existing meta links.
	 * @param string $file  Plugin filename.
	 * @return array
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( $file === ONUEXRA_BASENAME ) {
			$new_links = array(
				'support' => '<a href="https://buymeacoffee.com/constantinonu" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support', 'curs-bnr-convertor-valutar' ) . '</a>',
			);
			$links = array_merge( $links, $new_links );
		}
		return $links;
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public static function register_settings() {
		register_setting( 'onuexra_settings_group', 'onuexra_fetch_time', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_time' ),
			'default'           => '13:15',
		) );

		register_setting( 'onuexra_settings_group', 'onuexra_default_currencies', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_currencies' ),
			'default'           => array( 'EUR', 'USD', 'GBP', 'CHF' ),
		) );

		register_setting( 'onuexra_settings_group', 'onuexra_decimals', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 4,
		) );

		register_setting( 'onuexra_settings_group', 'onuexra_decimal_sep', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => ',',
		) );

		register_setting( 'onuexra_settings_group', 'onuexra_thousands_sep', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '.',
		) );

		register_setting( 'onuexra_settings_group', 'onuexra_keep_history_days', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );

		register_setting( 'onuexra_settings_group', 'onuexra_delete_on_uninstall', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		) );

		add_settings_section(
			'onuexra_sync_section',
			__( 'Sync Settings', 'curs-bnr-convertor-valutar' ),
			null,
			'onuexra-settings'
		);

		add_settings_section(
			'onuexra_display_section',
			__( 'Display & Formatting Settings', 'curs-bnr-convertor-valutar' ),
			null,
			'onuexra-settings'
		);

		add_settings_field(
			'onuexra_fetch_time',
			__( 'Daily Update Time', 'curs-bnr-convertor-valutar' ),
			array( __CLASS__, 'field_fetch_time_html' ),
			'onuexra-settings',
			'onuexra_sync_section'
		);

		add_settings_field(
			'onuexra_keep_history_days',
			__( 'Keep History (days)', 'curs-bnr-convertor-valutar' ),
			array( __CLASS__, 'field_keep_history_html' ),
			'onuexra-settings',
			'onuexra_sync_section'
		);

		add_settings_field(
			'onuexra_delete_on_uninstall',
			__( 'Delete Data on Uninstall', 'curs-bnr-convertor-valutar' ),
			array( __CLASS__, 'field_delete_on_uninstall_html' ),
			'onuexra-settings',
			'onuexra_sync_section'
		);

		add_settings_field(
			'onuexra_default_currencies',
			__( 'Active / Default Currencies', 'curs-bnr-convertor-valutar' ),
			array( __CLASS__, 'field_currencies_html' ),
			'onuexra-settings',
			'onuexra_display_section'
		);

		add_settings_field(
			'onuexra_decimals',
			__( 'Number of Decimals', 'curs-bnr-convertor-valutar' ),
			array( __CLASS__, 'field_decimals_html' ),
			'onuexra-settings',
			'onuexra_display_section'
		);

		add_settings_field(
			'onuexra_format_seps',
			__( 'Formatting Separators', 'curs-bnr-convertor-valutar' ),
			array( __CLASS__, 'field_seps_html' ),
			'onuexra-settings',
			'onuexra_display_section'
		);
	}

	/**
	 * Validate and sanitize input time format.
	 *
	 * @param string $time Time.
	 * @return string Sanitized time or default.
	 */
	public static function sanitize_time( $time ) {
		if ( preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) {
			add_action( 'update_option_onuexra_fetch_time', array( 'OnuExra\Cron', 'reschedule' ) );
			return $time;
		}
		return '13:15';
	}

	/**
	 * Sanitize currency array selection.
	 *
	 * @param array $currencies Selected currencies.
	 * @return array
	 */
	public static function sanitize_currencies( $currencies ) {
		if ( ! is_array( $currencies ) ) {
			return array( 'EUR', 'USD', 'GBP', 'CHF' );
		}

		$valid_currencies = array(
			'AED', 'AUD', 'BGN', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EGP',
			'EUR', 'GBP', 'HRK', 'HUF', 'INR', 'JPY', 'KRW', 'MDL', 'MXN', 'MYR',
			'NOK', 'NZD', 'PHP', 'PLN', 'RSD', 'RUB', 'SEK', 'THB', 'TRY', 'UAH',
			'USD', 'XAU', 'XDR', 'ZAR'
		);

		$sanitized = array();
		foreach ( $currencies as $currency ) {
			$clean = strtoupper( sanitize_key( $currency ) );
			if ( preg_match( '/^[A-Z]{3}$/', $clean ) && in_array( $clean, $valid_currencies, true ) ) {
				$sanitized[] = $clean;
			}
		}

		if ( empty( $sanitized ) ) {
			return array( 'EUR', 'USD', 'GBP', 'CHF' );
		}

		return $sanitized;
	}

	/**
	 * HTML fields.
	 */
	public static function field_fetch_time_html() {
		$time = get_option( 'onuexra_fetch_time', '13:15' );
		echo '<input type="time" name="onuexra_fetch_time" value="' . esc_attr( $time ) . '" />';
		echo '<p class="description">' . esc_html__( 'BNR typically publishes rates around 13:00 - 13:15.', 'curs-bnr-convertor-valutar' ) . '</p>';
	}

	public static function field_keep_history_html() {
		$days = intval( get_option( 'onuexra_keep_history_days', 0 ) );
		echo '<input type="number" name="onuexra_keep_history_days" min="0" step="1" value="' . esc_attr( $days ) . '" />';
		echo '<p class="description">' . esc_html__( '0 to keep all history. Any value greater than 0 will automatically delete data older than N days during daily sync.', 'curs-bnr-convertor-valutar' ) . '</p>';
	}

	public static function field_delete_on_uninstall_html() {
		$checked = get_option( 'onuexra_delete_on_uninstall', false );
		echo '<input type="checkbox" name="onuexra_delete_on_uninstall" value="1" ' . checked( $checked, true, false ) . ' />';
		echo '<p class="description">' . esc_html__( 'If checked, all exchange rates stored in the database and plugin settings will be permanently deleted when you uninstall the plugin.', 'curs-bnr-convertor-valutar' ) . '</p>';
	}

	public static function field_currencies_html() {
		$selected = get_option( 'onuexra_default_currencies', array( 'EUR', 'USD', 'GBP', 'CHF' ) );
		if ( ! is_array( $selected ) ) {
			$selected = array( 'EUR', 'USD', 'GBP', 'CHF' );
		}

		$all_currencies = array(
			'EUR', 'USD', 'GBP', 'CHF', 'HUF', 'BGN', 'CAD', 'JPY', 'MDL', 'RUB', 
			'TRY', 'UAH', 'AUD', 'PLN', 'SEK', 'NOK', 'DKK', 'CZK', 'DKK', 'ILS'
		);

		$db_currencies = DB::get_active_currencies();
		$all_currencies = array_unique( array_merge( $all_currencies, $db_currencies ) );
		sort( $all_currencies );

		echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 5px; max-width: 600px;">';
		foreach ( $all_currencies as $currency ) {
			if ( 'RON' === $currency ) {
				continue;
			}
			$checked = in_array( $currency, $selected, true );
			echo '<label><input type="checkbox" name="onuexra_default_currencies[]" value="' . esc_attr( $currency ) . '" ' . checked( $checked, true, false ) . ' /> ' . esc_html( $currency ) . '</label>';
		}
		echo '</div>';
	}

	public static function field_decimals_html() {
		$decimals = intval( get_option( 'onuexra_decimals', 4 ) );
		echo '<input type="number" name="onuexra_decimals" min="0" max="8" value="' . esc_attr( $decimals ) . '" />';
	}

	public static function field_seps_html() {
		$dec_point = get_option( 'onuexra_decimal_sep', ',' );
		$thousands = get_option( 'onuexra_thousands_sep', '.' );
		echo '<label>' . esc_html__( 'Decimal:', 'curs-bnr-convertor-valutar' ) . ' <input type="text" style="width: 50px;" name="onuexra_decimal_sep" value="' . esc_attr( $dec_point ) . '" /></label> &nbsp; ';
		echo '<label>' . esc_html__( 'Thousands:', 'curs-bnr-convertor-valutar' ) . ' <input type="text" style="width: 50px;" name="onuexra_thousands_sep" value="' . esc_attr( $thousands ) . '" /></label>';
	}

	/**
	 * Render Settings Page.
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action_notice = null;
		if ( isset( $_POST['onuexra_manual_fetch'] ) && isset( $_POST['onuexra_nonce'] ) ) {
			if ( wp_verify_nonce( sanitize_key( $_POST['onuexra_nonce'] ), 'onuexra_action_nonce' ) ) {
				$result = Fetcher::fetch_daily();
				if ( is_wp_error( $result ) ) {
					$action_notice = array(
						'type'    => 'error',
						'message' => __( 'Sync error: ', 'curs-bnr-convertor-valutar' ) . $result->get_error_message(),
					);
				} else {
					$action_notice = array(
						'type'    => 'success',
						'message' => sprintf(
							/* translators: 1: number of dates, 2: number of rates */
							__( 'Sync completed successfully. Processed %1$d date(s) and saved %2$d rate(s).', 'curs-bnr-convertor-valutar' ),
							$result['dates_processed'],
							$result['rates_saved']
						),
					);
				}
			}
		}

		if ( isset( $_POST['onuexra_import_history'] ) && isset( $_POST['onuexra_nonce'] ) ) {
			if ( wp_verify_nonce( sanitize_key( $_POST['onuexra_nonce'] ), 'onuexra_action_nonce' ) ) {
				$year = isset( $_POST['onuexra_import_year'] ) ? intval( $_POST['onuexra_import_year'] ) : 0;
				if ( $year >= 2005 && $year <= intval( gmdate( 'Y' ) ) ) {
					$result = Fetcher::fetch_year( $year );
					if ( is_wp_error( $result ) ) {
						$action_notice = array(
							'type'    => 'error',
							/* translators: 1: year, 2: error message */
							'message' => sprintf( __( 'Error importing year %1$d: %2$s', 'curs-bnr-convertor-valutar' ), $year, $result->get_error_message() ),
						);
					} else {
						$action_notice = array(
							'type'    => 'success',
							'message' => sprintf(
								/* translators: 1: year, 2: number of dates, 3: number of rates */
								__( 'Import of year %1$d completed. Processed %2$d date(s) and saved %3$d rate(s).', 'curs-bnr-convertor-valutar' ),
								$year,
								$result['dates_processed'],
								$result['rates_saved']
							),
						);
					}
				}
			}
		}

		$logs = get_option( 'onuexra_fetch_logs', array() );
		?>
		<div class="wrap onuexra-admin-wrap" style="max-width: 1200px;">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( $action_notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $action_notice['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $action_notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="onuexra-admin-columns" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
				<div class="onuexra-admin-main" style="flex: 2; min-width: 300px;">
					<form method="post" action="options.php">
						<?php
						settings_fields( 'onuexra_settings_group' );
						do_settings_sections( 'onuexra-settings' );
						submit_button();
						?>
					</form>

					<hr style="margin: 40px 0 20px 0;" />

					<div class="card" style="max-width: 100%; margin-top: 20px; padding: 15px 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
						<h2><?php esc_html_e( 'Manual Actions & Diagnostics', 'curs-bnr-convertor-valutar' ); ?></h2>
						<p><?php esc_html_e( 'Use the buttons below to trigger an immediate sync or to test the connection with the BNR XML server.', 'curs-bnr-convertor-valutar' ); ?></p>

						<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">
							<form method="post" action="">
								<?php wp_nonce_field( 'onuexra_action_nonce', 'onuexra_nonce' ); ?>
								<input type="submit" name="onuexra_manual_fetch" class="button button-primary" value="<?php esc_attr_e( 'Update Rates Now (Today)', 'curs-bnr-convertor-valutar' ); ?>" />
							</form>
						</div>

						<hr />

						<h3><?php esc_html_e( 'Import Yearly History', 'curs-bnr-convertor-valutar' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Import all BNR exchange rates published in a specific year (starting from 2005) into the local database.', 'curs-bnr-convertor-valutar' ); ?></p>
						<form method="post" action="" style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
							<?php wp_nonce_field( 'onuexra_action_nonce', 'onuexra_nonce' ); ?>
							<select name="onuexra_import_year">
								<?php
								$current_year = intval( gmdate( 'Y' ) );
								for ( $y = $current_year; $y >= 2005; $y-- ) {
									echo '<option value="' . esc_attr( $y ) . '">' . esc_html( $y ) . '</option>';
								}
								?>
							</select>
							<input type="submit" name="onuexra_import_history" class="button button-secondary" value="<?php esc_attr_e( 'Import Selected Year', 'curs-bnr-convertor-valutar' ); ?>" />
						</form>
					</div>

					<div style="margin-top: 40px;">
						<h2><?php esc_html_e( 'Last 10 Sync History', 'curs-bnr-convertor-valutar' ); ?></h2>
						<?php if ( empty( $logs ) ) : ?>
							<p><?php esc_html_e( 'No logs recorded yet.', 'curs-bnr-convertor-valutar' ); ?></p>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
								<thead>
									<tr>
										<th style="width: 20%;"><?php esc_html_e( 'Execution Date', 'curs-bnr-convertor-valutar' ); ?></th>
										<th style="width: 15%;"><?php esc_html_e( 'Rate Type', 'curs-bnr-convertor-valutar' ); ?></th>
										<th style="width: 15%;"><?php esc_html_e( 'Status', 'curs-bnr-convertor-valutar' ); ?></th>
										<th><?php esc_html_e( 'Message / Error', 'curs-bnr-convertor-valutar' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $logs as $log ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $log['time'] ); ?></strong></td>
											<td><?php echo esc_html( $log['date'] ); ?></td>
											<td>
												<?php if ( 'success' === $log['status'] ) : ?>
													<span style="color: #46b450; font-weight: bold;">● <?php esc_html_e( 'Success', 'curs-bnr-convertor-valutar' ); ?></span>
												<?php else : ?>
													<span style="color: #dc3232; font-weight: bold;">● <?php esc_html_e( 'Error', 'curs-bnr-convertor-valutar' ); ?></span>
												<?php endif; ?>
											</td>
											<td><?php echo esc_html( $log['message'] ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>

				<div class="onuexra-admin-sidebar" style="flex: 1; min-width: 250px; max-width: 350px;">
					<div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px; text-align: center;">
						<span style="font-size: 50px; line-height: 1;">☕</span>
						<h2 style="margin-top: 10px; font-size: 1.3em;"><?php esc_html_e( 'Support Development', 'curs-bnr-convertor-valutar' ); ?></h2>
						<p style="font-size: 13px; line-height: 1.5; color: #50575e;">
							<?php esc_html_e( 'This plugin is provided for free and is constantly updated to ensure compatibility and security for your site.', 'curs-bnr-convertor-valutar' ); ?>
						</p>
						<p style="font-size: 13px; line-height: 1.5; color: #50575e;">
							<?php esc_html_e( 'If it helps you in your daily work, you can support the author by buying them a virtual coffee. Any donation is greatly appreciated!', 'curs-bnr-convertor-valutar' ); ?>
						</p>
						<div style="margin-top: 20px;">
							<a href="https://buymeacoffee.com/constantinonu" target="_blank" rel="noopener noreferrer" class="button button-primary button-large" style="background: #FF813F; border-color: #FF813F; text-shadow: none; font-weight: bold;">
								<?php esc_html_e( '☕ Buy me a coffee', 'curs-bnr-convertor-valutar' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

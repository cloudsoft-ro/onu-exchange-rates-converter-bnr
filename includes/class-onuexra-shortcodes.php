<?php
/**
 * Shortcodes Registration and Rendering.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

namespace OnuExra;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcodes
 */
class Shortcodes {

	public static function init() {
		add_shortcode( 'onuexra_curs', array( __CLASS__, 'render_curs' ) );
		add_shortcode( 'onuexra_convertor', array( __CLASS__, 'render_convertor' ) );
		add_shortcode( 'onuexra_grafic', array( __CLASS__, 'render_grafic' ) );
		add_shortcode( 'onuexra_chart', array( __CLASS__, 'render_grafic' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'init', array( __CLASS__, 'register_gutenberg_block' ) );
	}

	/**
	 * Format exchange rate based on plugin settings.
	 *
	 * @param float $value Numerical rate.
	 * @return string Formatted string.
	 */
	public static function format_rate( $value ) {
		$decimals      = intval( get_option( 'onuexra_decimals', 4 ) );
		$dec_point     = get_option( 'onuexra_decimal_sep', ',' );
		$thousands_sep = get_option( 'onuexra_thousands_sep', '.' );
		return number_format( $value, $decimals, $dec_point, $thousands_sep );
	}

	/**
	 * Enqueue assets (CSS + JS).
	 */
	public static function enqueue_assets() {
		wp_enqueue_style(
			'onuexra-style',
			ONUEXRA_URL . 'assets/css/onuexra-style.css',
			array(),
			ONUEXRA_VERSION
		);

		wp_register_script(
			'onuexra-convertor',
			ONUEXRA_URL . 'assets/js/onuexra-convertor.js',
			array(),
			ONUEXRA_VERSION,
			true
		);

		wp_register_script(
			'onuexra-chart',
			ONUEXRA_URL . 'assets/js/onuexra-chart.js',
			array(),
			ONUEXRA_VERSION,
			true
		);

		$rates = DB::get_rates();
		wp_localize_script(
			'onuexra-convertor',
			'onuexraData',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'onuexra/v1' ) ),
				'rates'      => $rates,
				'decimalSep' => get_option( 'onuexra_decimal_sep', ',' ),
				'thousandSep'=> get_option( 'onuexra_thousands_sep', '.' ),
				'decimals'   => intval( get_option( 'onuexra_decimals', 4 ) ),
			)
		);

		wp_localize_script(
			'onuexra-chart',
			'onuexraChartData',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'onuexra/v1' ) ),
				'decimalSep'  => get_option( 'onuexra_decimal_sep', ',' ),
				'thousandSep' => get_option( 'onuexra_thousands_sep', '.' ),
				'decimals'    => intval( get_option( 'onuexra_decimals', 4 ) ),
			)
		);
	}

	/**
	 * Render the BNR exchange rates shortcode `[onuexra_curs]`.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Output HTML.
	 */
	public static function render_curs( $atts ) {
		$default_currencies = get_option( 'onuexra_default_currencies', array( 'EUR', 'USD', 'GBP', 'CHF' ) );
		if ( empty( $default_currencies ) || ! is_array( $default_currencies ) ) {
			$default_currencies = array( 'EUR', 'USD', 'GBP', 'CHF' );
		}

		$atts = shortcode_atts(
			array(
				'valute'         => implode( ',', $default_currencies ),
				'data'           => '',
				'stil'           => 'tabel',
				'arata_variatie' => 'da',
				'tema'           => 'implicit',
				'densitate'      => 'implicit',
			),
			$atts,
			'onuexra_curs'
		);

		$currencies     = array_map( 'trim', explode( ',', $atts['valute'] ) );
		$currencies     = array_map( 'strtoupper', $currencies );
		$date           = sanitize_text_field( $atts['data'] );
		$style          = sanitize_key( $atts['stil'] );
		$show_variation = 'da' === $atts['arata_variatie'];

		$theme = sanitize_key( $atts['tema'] );
		if ( ! in_array( $theme, array( 'light', 'dark' ), true ) ) {
			$theme = get_option( 'onuexra_theme', 'light' );
		}

		$layout_style = sanitize_key( $atts['densitate'] );
		if ( ! in_array( $layout_style, array( 'compact', 'normal', 'spacious' ), true ) ) {
			$layout_style = get_option( 'onuexra_layout_style', 'normal' );
		}

		wp_enqueue_style( 'onuexra-style' );

		$rates = DB::get_rates( $date );
		if ( empty( $rates ) ) {
			return '<p class="onuexra-error">' . esc_html__( 'No exchange rates available for the requested date.', 'curs-bnr-convertor-valutar' ) . '</p>';
		}

		$resolved_date = '';
		$sample_currency = reset( $currencies );
		if ( $sample_currency && isset( $rates[ $sample_currency ] ) ) {
			$rate_info = DB::get_rate( $sample_currency, $date );
			if ( $rate_info && isset( $rate_info['date'] ) ) {
				$resolved_date = $rate_info['date'];
			}
		}
		if ( ! $resolved_date ) {
			$resolved_date = $date ? $date : current_time( 'Y-m-d' );
		}

		$display_date = date_i18n( get_option( 'date_format' ), strtotime( $resolved_date ) );

		ob_start();
		?>
		<div class="onuexra-rates-container onuexra-style-<?php echo esc_attr( $style ); ?> onuexra-theme-<?php echo esc_attr( $theme ); ?> onuexra-layout-<?php echo esc_attr( $layout_style ); ?>">
			<div class="onuexra-rates-header">
				<span class="onuexra-header-title"><?php esc_html_e( 'BNR Exchange Rates', 'curs-bnr-convertor-valutar' ); ?></span>
				<span class="onuexra-header-date"><?php echo esc_html( $display_date ); ?></span>
			</div>

			<?php if ( 'tabel' === $style ) : ?>
				<table class="onuexra-rates-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Currency', 'curs-bnr-convertor-valutar' ); ?></th>
							<th><?php esc_html_e( 'Rate', 'curs-bnr-convertor-valutar' ); ?></th>
							<?php if ( $show_variation ) : ?>
								<th class="onuexra-col-variation"><?php esc_html_e( 'Variation', 'curs-bnr-convertor-valutar' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $currencies as $currency ) {
							if ( 'RON' === $currency || ! isset( $rates[ $currency ] ) ) {
								continue;
							}
							$rate_val   = $rates[ $currency ]['rate'];
							$multiplier = $rates[ $currency ]['multiplier'];
							$display_val = $rate_val;

							$label = $currency;
							if ( $multiplier > 1 ) {
								$label = $multiplier . ' ' . $currency;
							}

							$variation_html = '';
							if ( $show_variation ) {
								$variation_html = self::get_variation_badge( $currency, $resolved_date, $rate_val );
							}
							?>
							<tr>
								<td class="onuexra-currency-label"><?php echo esc_html( $label ); ?></td>
								<td class="onuexra-currency-value"><?php echo esc_html( self::format_rate( $display_val ) ); ?> <span class="onuexra-base-currency">RON</span></td>
								<?php if ( $show_variation ) : ?>
									<td class="onuexra-currency-variation"><?php echo $variation_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<?php endif; ?>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>

			<?php elseif ( 'card' === $style ) : ?>
				<div class="onuexra-rates-grid">
					<?php
					foreach ( $currencies as $currency ) {
						if ( 'RON' === $currency || ! isset( $rates[ $currency ] ) ) {
							continue;
						}
						$rate_val   = $rates[ $currency ]['rate'];
						$multiplier = $rates[ $currency ]['multiplier'];
						$label      = $currency;
						if ( $multiplier > 1 ) {
							$label = $multiplier . ' ' . $currency;
						}

						$variation_html = '';
						if ( $show_variation ) {
							$variation_html = self::get_variation_badge( $currency, $resolved_date, $rate_val );
						}
						?>
						<div class="onuexra-rate-card">
							<div class="onuexra-card-currency"><?php echo esc_html( $label ); ?></div>
							<div class="onuexra-card-value"><?php echo esc_html( self::format_rate( $rate_val ) ); ?> <span class="onuexra-base-currency">RON</span></div>
							<?php if ( $show_variation ) : ?>
								<div class="onuexra-card-variation"><?php echo $variation_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<?php endif; ?>
						</div>
						<?php
					}
					?>
				</div>

			<?php elseif ( 'inline' === $style ) : ?>
				<div class="onuexra-rates-inline">
					<?php
					$inline_items = array();
					foreach ( $currencies as $currency ) {
						if ( 'RON' === $currency || ! isset( $rates[ $currency ] ) ) {
							continue;
						}
						$rate_val   = $rates[ $currency ]['rate'];
						$multiplier = $rates[ $currency ]['multiplier'];
						$label      = $currency;
						if ( $multiplier > 1 ) {
							$label = $multiplier . ' ' . $currency;
						}

						$variation_text = '';
						if ( $show_variation ) {
							$variation_text = self::get_variation_text( $currency, $resolved_date, $rate_val );
						}

						$item_html = sprintf(
							'<span class="onuexra-inline-item"><strong>%s</strong>: %s RON%s</span>',
							esc_html( $label ),
							esc_html( self::format_rate( $rate_val ) ),
							$variation_text ? ' ' . $variation_text : ''
						);
						$inline_items[] = $item_html;
					}
					echo implode( ' <span class="onuexra-inline-separator">•</span> ', $inline_items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the currency converter shortcode `[onuexra_convertor]`.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Output HTML.
	 */
	public static function render_convertor( $atts ) {
		$atts = shortcode_atts(
			array(
				'de_la'     => 'EUR',
				'catre'     => 'RON',
				'tema'      => 'implicit',
				'densitate' => 'implicit',
			),
			$atts,
			'onuexra_convertor'
		);

		wp_enqueue_style( 'onuexra-style' );
		wp_enqueue_script( 'onuexra-convertor' );

		$from_currency = strtoupper( sanitize_key( $atts['de_la'] ) );
		$to_currency   = strtoupper( sanitize_key( $atts['catre'] ) );

		$theme = sanitize_key( $atts['tema'] );
		if ( ! in_array( $theme, array( 'light', 'dark' ), true ) ) {
			$theme = get_option( 'onuexra_theme', 'light' );
		}

		$layout_style = sanitize_key( $atts['densitate'] );
		if ( ! in_array( $layout_style, array( 'compact', 'normal', 'spacious' ), true ) ) {
			$layout_style = get_option( 'onuexra_layout_style', 'normal' );
		}

		$currencies = DB::get_active_currencies();
		if ( ! in_array( 'RON', $currencies, true ) ) {
			$currencies[] = 'RON';
			sort( $currencies );
		}

		ob_start();
		?>
		<div class="onuexra-convertor-wrapper onuexra-rates-container onuexra-theme-<?php echo esc_attr( $theme ); ?> onuexra-layout-<?php echo esc_attr( $layout_style ); ?>">
			<div class="onuexra-rates-header">
				<span class="onuexra-header-title"><?php esc_html_e( 'BNR Currency Converter', 'curs-bnr-convertor-valutar' ); ?></span>
			</div>

			<form class="onuexra-convertor-form" id="onuexra-convertor-form">
				<div class="onuexra-form-row">
					<div class="onuexra-form-group onuexra-group-amount">
						<label for="onuexra-amount"><?php esc_html_e( 'Amount', 'curs-bnr-convertor-valutar' ); ?></label>
						<input type="number" id="onuexra-amount" step="any" min="0" value="1" />
					</div>

					<div class="onuexra-form-group onuexra-group-from">
						<label for="onuexra-from"><?php esc_html_e( 'From', 'curs-bnr-convertor-valutar' ); ?></label>
						<select id="onuexra-from">
							<?php foreach ( $currencies as $currency ) : ?>
								<option value="<?php echo esc_attr( $currency ); ?>" <?php selected( $from_currency, $currency ); ?>><?php echo esc_html( $currency ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<button type="button" class="onuexra-swap-btn" id="onuexra-swap-btn" title="<?php esc_attr_e( 'Swap currencies', 'curs-bnr-convertor-valutar' ); ?>">
						<span class="onuexra-swap-icon">⇄</span>
					</button>

					<div class="onuexra-form-group onuexra-group-to">
						<label for="onuexra-to"><?php esc_html_e( 'To', 'curs-bnr-convertor-valutar' ); ?></label>
						<select id="onuexra-to">
							<?php foreach ( $currencies as $currency ) : ?>
								<option value="<?php echo esc_attr( $currency ); ?>" <?php selected( $to_currency, $currency ); ?>><?php echo esc_html( $currency ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="onuexra-form-row onuexra-date-row">
					<div class="onuexra-form-group onuexra-group-date">
						<label for="onuexra-conv-date"><?php esc_html_e( 'Rate date (optional)', 'curs-bnr-convertor-valutar' ); ?></label>
						<input type="date" id="onuexra-conv-date" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" />
					</div>
				</div>

				<div class="onuexra-result-container">
					<div class="onuexra-result-calculation" id="onuexra-result-calculation">-</div>
					<div class="onuexra-result-date" id="onuexra-result-date"></div>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Compute the variation HTML badge comparing current and previous rate.
	 *
	 * @param string $currency Currency code.
	 * @param string $date     Current rate date.
	 * @param float  $rate     Current rate value.
	 * @return string HTML output.
	 */
	public static function get_variation_badge( $currency, $date, $rate ) {
		$prev = DB::get_previous_rate( $currency, $date );
		if ( ! $prev ) {
			return '<span class="onuexra-variation onuexra-var-none">—</span>';
		}

		$prev_rate = floatval( $prev['rate'] );
		if ( $prev_rate <= 0 ) {
			return '<span class="onuexra-variation onuexra-var-none">—</span>';
		}

		$diff     = $rate - $prev_rate;
		$pct      = ( $diff / $prev_rate ) * 100;
		$sign     = $diff >= 0 ? '+' : '';
		$pct_text = $sign . number_format( $pct, 2, ',', '.' ) . '%';

		if ( $diff > 0 ) {
			return sprintf( '<span class="onuexra-variation onuexra-var-up" title="%s">▲ %s</span>', esc_attr( self::format_rate( $diff ) ), esc_html( $pct_text ) );
		} elseif ( $diff < 0 ) {
			return sprintf( '<span class="onuexra-variation onuexra-var-down" title="%s">▼ %s</span>', esc_attr( self::format_rate( $diff ) ), esc_html( $pct_text ) );
		} else {
			return '<span class="onuexra-variation onuexra-var-stable">0,00%</span>';
		}
	}

	/**
	 * Compute the variation plain text comparing current and previous rate.
	 *
	 * @param string $currency Currency code.
	 * @param string $date     Current rate date.
	 * @param float  $rate     Current rate value.
	 * @return string Plain text format.
	 */
	public static function get_variation_text( $currency, $date, $rate ) {
		$prev = DB::get_previous_rate( $currency, $date );
		if ( ! $prev ) {
			return '';
		}

		$prev_rate = floatval( $prev['rate'] );
		if ( $prev_rate <= 0 ) {
			return '';
		}

		$diff = $rate - $prev_rate;
		$pct  = ( $diff / $prev_rate ) * 100;
		$sign = $diff >= 0 ? '+' : '';
		$text = $sign . number_format( $pct, 2, ',', '.' ) . '%';

		if ( $diff > 0 ) {
			return sprintf( '<span class="onuexra-text-up">(↑ %s)</span>', esc_html( $text ) );
		} elseif ( $diff < 0 ) {
			return sprintf( '<span class="onuexra-text-down">(↓ %s)</span>', esc_html( $text ) );
		} else {
			return '<span class="onuexra-text-stable">(~ 0,00%)</span>';
		}
	}

	/**
	 * Register Gutenberg Blocks.
	 */
	public static function register_gutenberg_block() {
		register_block_type(
			ONUEXRA_PATH . 'blocks/curs-valutar',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);

		register_block_type(
			ONUEXRA_PATH . 'blocks/convertor-valutar',
			array(
				'render_callback' => array( __CLASS__, 'render_convertor_block' ),
			)
		);

		register_block_type(
			ONUEXRA_PATH . 'blocks/grafic-valutar',
			array(
				'render_callback' => array( __CLASS__, 'render_chart_block' ),
			)
		);
	}

	/**
	 * Render callback for the Convertor Gutenberg block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public static function render_convertor_block( $attributes ) {
		$de_la     = isset( $attributes['de_la'] ) ? $attributes['de_la'] : 'EUR';
		$catre     = isset( $attributes['catre'] ) ? $attributes['catre'] : 'RON';
		$tema      = isset( $attributes['tema'] ) ? $attributes['tema'] : 'implicit';
		$densitate = isset( $attributes['densitate'] ) ? $attributes['densitate'] : 'implicit';

		return self::render_convertor(
			array(
				'de_la'     => $de_la,
				'catre'     => $catre,
				'tema'      => $tema,
				'densitate' => $densitate,
			)
		);
	}

	/**
	 * Render callback for the Chart Gutenberg block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public static function render_chart_block( $attributes ) {
		$valuta             = isset( $attributes['valuta'] ) ? $attributes['valuta'] : 'EUR';
		$perioada           = isset( $attributes['perioada'] ) ? $attributes['perioada'] : '1luna';
		$valute             = isset( $attributes['valute'] ) ? $attributes['valute'] : 'EUR,USD,GBP,CHF';
		$comutator_perioade = isset( $attributes['comutator_perioade'] ) && ! $attributes['comutator_perioade'] ? 'nu' : 'da';
		$comutator_valute   = isset( $attributes['comutator_valute'] ) && ! $attributes['comutator_valute'] ? 'nu' : 'da';
		$titlu              = isset( $attributes['titlu'] ) ? $attributes['titlu'] : '';
		$inaltime           = isset( $attributes['inaltime'] ) ? intval( $attributes['inaltime'] ) : 300;
		$tema               = isset( $attributes['tema'] ) ? $attributes['tema'] : 'implicit';
		$densitate          = isset( $attributes['densitate'] ) ? $attributes['densitate'] : 'implicit';

		return self::render_grafic(
			array(
				'valuta'             => $valuta,
				'perioada'           => $perioada,
				'valute'             => $valute,
				'comutator_perioade' => $comutator_perioade,
				'comutator_valute'   => $comutator_valute,
				'titlu'              => $titlu,
				'inaltime'           => $inaltime,
				'tema'               => $tema,
				'densitate'          => $densitate,
			)
		);
	}

	/**
	 * Render callback for Gutenberg block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public static function render_block( $attributes ) {
		$valute = isset( $attributes['valute'] ) ? $attributes['valute'] : '';
		if ( empty( $valute ) ) {
			$default = get_option( 'onuexra_default_currencies', array( 'EUR', 'USD', 'GBP', 'CHF' ) );
			$valute = is_array( $default ) ? implode( ',', $default ) : $default;
		}

		$stil           = isset( $attributes['stil'] ) ? $attributes['stil'] : 'tabel';
		$arata_variatie = isset( $attributes['arata_variatie'] ) && $attributes['arata_variatie'] ? 'da' : 'nu';
		$tema           = isset( $attributes['tema'] ) ? $attributes['tema'] : 'implicit';
		$densitate      = isset( $attributes['densitate'] ) ? $attributes['densitate'] : 'implicit';

		return self::render_curs(
			array(
				'valute'         => $valute,
				'stil'           => $stil,
				'arata_variatie' => $arata_variatie,
				'tema'           => $tema,
				'densitate'      => $densitate,
			)
		);
	}

	/**
	 * Render BNR Exchange Rates Chart `[onuexra_grafic]`.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_grafic( $atts ) {
		$atts = shortcode_atts(
			array(
				'valuta'             => 'EUR',
				'perioada'           => '1luna',
				'valute'             => 'EUR,USD,GBP,CHF',
				'comutator_perioade' => 'da',
				'comutator_valute'   => 'da',
				'titlu'              => '',
				'inaltime'           => 300,
				'tema'               => 'implicit',
				'densitate'          => 'implicit',
			),
			$atts,
			'onuexra_grafic'
		);

		wp_enqueue_style( 'onuexra-style' );
		wp_enqueue_script( 'onuexra-chart' );

		$currency           = strtoupper( sanitize_text_field( $atts['valuta'] ) );
		$period             = DB::normalize_period( $atts['perioada'] );
		$show_period_switch = 'da' === $atts['comutator_perioade'];
		$show_curr_switch   = 'da' === $atts['comutator_valute'];
		$height             = max( 180, min( 600, intval( $atts['inaltime'] ) ) );

		$theme = sanitize_key( $atts['tema'] );
		if ( ! in_array( $theme, array( 'light', 'dark' ), true ) ) {
			$theme = get_option( 'onuexra_theme', 'light' );
		}

		$layout_style = sanitize_key( $atts['densitate'] );
		if ( ! in_array( $layout_style, array( 'compact', 'normal', 'spacious' ), true ) ) {
			$layout_style = get_option( 'onuexra_layout_style', 'normal' );
		}

		// Available currencies
		$allowed_currencies_raw = array_map( 'trim', explode( ',', $atts['valute'] ) );
		$allowed_currencies     = array();
		foreach ( $allowed_currencies_raw as $c ) {
			$clean_c = strtoupper( sanitize_text_field( $c ) );
			if ( strlen( $clean_c ) === 3 && 'RON' !== $clean_c ) {
				$allowed_currencies[] = $clean_c;
			}
		}
		if ( ! in_array( $currency, $allowed_currencies, true ) ) {
			array_unshift( $allowed_currencies, $currency );
		}

		// Period buttons definitions
		$periods = array(
			'7zile' => __( '7 Zile', 'curs-bnr-convertor-valutar' ),
			'1luna' => __( '1 Lună', 'curs-bnr-convertor-valutar' ),
			'3luni' => __( '3 Luni', 'curs-bnr-convertor-valutar' ),
			'1an'   => __( '1 An', 'curs-bnr-convertor-valutar' ),
			'5ani'  => __( '5 Ani', 'curs-bnr-convertor-valutar' ),
		);

		$end_date   = current_time( 'Y-m-d' );
		$start_date = DB::calculate_start_date( $period, $end_date );

		// Retrieve initial dataset from DB
		$rates = DB::get_historical_rates( $currency, $start_date, $end_date );

		// Auto-heal: If empty, sync daily rates
		if ( empty( $rates ) ) {
			$current_year = intval( gmdate( 'Y' ) );
			if ( ! DB::has_historical_data_for_year( $current_year ) ) {
				$lock = get_transient( 'onuexra_syncing_current_year' );
				if ( false === $lock ) {
					set_transient( 'onuexra_syncing_current_year', 1, 60 );
					Fetcher::fetch_daily();
					$rates = DB::get_historical_rates( $currency, $start_date, $end_date );
				}
			}
		}

		$multiplier = 1;
		if ( ! empty( $rates ) && isset( $rates[0]['multiplier'] ) ) {
			$multiplier = intval( $rates[0]['multiplier'] );
		}
		$label = $multiplier > 1 ? "$multiplier $currency" : $currency;

		$title = $atts['titlu'];
		if ( empty( $title ) ) {
			/* translators: %s: currency code */
			$title = sprintf( __( 'Evoluție Curs BNR – %s', 'curs-bnr-convertor-valutar' ), $label );
		}

		$initial_data_json = '';
		$current_rate_str  = '-';
		$diff_badge_html   = '';
		$min_str           = '-';
		$max_str           = '-';
		$avg_str           = '-';
		$period_span_str   = '';

		if ( ! empty( $rates ) ) {
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
					'formatted_rate' => self::format_rate( $row['rate'] ),
					'formatted_date' => date_i18n( 'j F Y', strtotime( $row['date'] ) ),
					'short_date'     => date_i18n( 'j M', strtotime( $row['date'] ) ),
				);
			}

			$current_rate_str = self::format_rate( $current_rate ) . ' RON';
			$diff_class       = $diff >= 0 ? 'onuexra-diff-up' : 'onuexra-diff-down';
			$diff_text        = $sign . self::format_rate( $diff ) . ' (' . $sign . number_format( $diff_pct, 2, ',', '.' ) . '%)';
			$diff_badge_html  = '<span class="onuexra-chart-current-diff ' . esc_attr( $diff_class ) . '">' . esc_html( $diff_text ) . '</span>';

			$min_str         = self::format_rate( $min_rate ) . ' RON';
			$max_str         = self::format_rate( $max_rate ) . ' RON';
			$avg_str         = self::format_rate( $avg_rate ) . ' RON';
			$period_span_str = date_i18n( 'j M Y', strtotime( $first_point['date'] ) ) . ' – ' . date_i18n( 'j M Y', strtotime( $last_point['date'] ) );

			$data_payload = array(
				'success'            => true,
				'currency'           => $currency,
				'label'              => $label,
				'multiplier'         => $multiplier,
				'period'             => $period,
				'start_date'         => $start_date,
				'end_date'           => $end_date,
				'count'              => count( $rates ),
				'current_rate'       => $current_rate,
				'start_rate'         => $start_rate,
				'min_rate'           => $min_rate,
				'max_rate'           => $max_rate,
				'avg_rate'           => round( $avg_rate, 4 ),
				'diff'               => $diff,
				'diff_pct'           => round( $diff_pct, 2 ),
				'is_positive'        => $diff >= 0,
				'formatted_current'  => self::format_rate( $current_rate ),
				'formatted_min'      => self::format_rate( $min_rate ),
				'formatted_max'      => self::format_rate( $max_rate ),
				'formatted_avg'      => self::format_rate( $avg_rate ),
				'formatted_diff'     => $sign . self::format_rate( $diff ),
				'formatted_diff_pct' => $sign . number_format( $diff_pct, 2, ',', '.' ) . '%',
				'points'             => $points,
			);

			$initial_data_json = wp_json_encode( $data_payload );
		}

		ob_start();
		?>
		<div class="onuexra-chart-wrapper onuexra-rates-container onuexra-theme-<?php echo esc_attr( $theme ); ?> onuexra-layout-<?php echo esc_attr( $layout_style ); ?>" 
			data-currency="<?php echo esc_attr( $currency ); ?>" 
			data-period="<?php echo esc_attr( $period ); ?>" 
			data-height="<?php echo esc_attr( $height ); ?>">
			
			<div class="onuexra-chart-top-bar">
				<div class="onuexra-chart-heading-group">
					<span class="onuexra-chart-title"><?php echo esc_html( $title ); ?></span>
					<?php if ( $show_curr_switch && count( $allowed_currencies ) > 1 ) : ?>
						<select class="onuexra-chart-currency-select" aria-label="<?php esc_attr_e( 'Select currency', 'curs-bnr-convertor-valutar' ); ?>">
							<?php foreach ( $allowed_currencies as $c_opt ) : ?>
								<option value="<?php echo esc_attr( $c_opt ); ?>" <?php selected( $currency, $c_opt ); ?>>
									<?php echo esc_html( $c_opt ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</div>

				<?php if ( $show_period_switch ) : ?>
					<div class="onuexra-chart-periods" role="group" aria-label="<?php esc_attr_e( 'Chart time period', 'curs-bnr-convertor-valutar' ); ?>">
						<?php foreach ( $periods as $p_key => $p_label ) : ?>
							<button type="button" 
								class="onuexra-period-btn <?php echo ( $period === $p_key ) ? 'is-active' : ''; ?>" 
								data-period="<?php echo esc_attr( $p_key ); ?>">
								<?php echo esc_html( $p_label ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="onuexra-chart-header-stats">
				<div class="onuexra-chart-hero-rate">
					<span class="onuexra-chart-current-value"><?php echo esc_html( $current_rate_str ); ?></span>
					<?php echo $diff_badge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>

			<div class="onuexra-chart-stats-grid">
				<div class="onuexra-stat-card">
					<div class="onuexra-stat-label"><?php esc_html_e( 'Minim', 'curs-bnr-convertor-valutar' ); ?></div>
					<div class="onuexra-stat-value onuexra-stat-min"><?php echo esc_html( $min_str ); ?></div>
				</div>
				<div class="onuexra-stat-card">
					<div class="onuexra-stat-label"><?php esc_html_e( 'Maxim', 'curs-bnr-convertor-valutar' ); ?></div>
					<div class="onuexra-stat-value onuexra-stat-max"><?php echo esc_html( $max_str ); ?></div>
				</div>
				<div class="onuexra-stat-card">
					<div class="onuexra-stat-label"><?php esc_html_e( 'Medie', 'curs-bnr-convertor-valutar' ); ?></div>
					<div class="onuexra-stat-value onuexra-stat-avg"><?php echo esc_html( $avg_str ); ?></div>
				</div>
				<div class="onuexra-stat-card">
					<div class="onuexra-stat-label"><?php esc_html_e( 'Variație', 'curs-bnr-convertor-valutar' ); ?></div>
					<div class="onuexra-stat-value onuexra-stat-change"><?php echo ! empty( $diff_text ) ? esc_html( $diff_text ) : '—'; ?></div>
				</div>
			</div>

			<div class="onuexra-chart-canvas-container" style="min-height: <?php echo esc_attr( $height ); ?>px;">
				<div class="onuexra-chart-loader">
					<div class="onuexra-chart-spinner"></div>
				</div>
				<?php if ( empty( $rates ) ) : ?>
					<div class="onuexra-chart-empty-msg">
						<?php esc_html_e( 'Nu există date disponibile pentru perioada selectată. Datele se vor actualiza automat.', 'curs-bnr-convertor-valutar' ); ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $initial_data_json ) ) : ?>
				<script type="application/json" class="onuexra-initial-chart-data"><?php echo $initial_data_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}


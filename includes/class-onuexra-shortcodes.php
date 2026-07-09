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
			),
			$atts,
			'onuexra_curs'
		);

		$currencies     = array_map( 'trim', explode( ',', $atts['valute'] ) );
		$currencies     = array_map( 'strtoupper', $currencies );
		$date           = sanitize_text_field( $atts['data'] );
		$style          = sanitize_key( $atts['stil'] );
		$show_variation = 'da' === $atts['arata_variatie'];

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
		<div class="onuexra-rates-container onuexra-style-<?php echo esc_attr( $style ); ?>">
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
				'de_la' => 'EUR',
				'catre' => 'RON',
			),
			$atts,
			'onuexra_convertor'
		);

		wp_enqueue_style( 'onuexra-style' );
		wp_enqueue_script( 'onuexra-convertor' );

		$from_currency = strtoupper( sanitize_key( $atts['de_la'] ) );
		$to_currency   = strtoupper( sanitize_key( $atts['catre'] ) );

		$currencies = DB::get_active_currencies();
		if ( ! in_array( 'RON', $currencies, true ) ) {
			$currencies[] = 'RON';
			sort( $currencies );
		}

		ob_start();
		?>
		<div class="onuexra-convertor-wrapper onuexra-rates-container">
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
	}

	/**
	 * Render callback for the Convertor Gutenberg block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public static function render_convertor_block( $attributes ) {
		$de_la = isset( $attributes['de_la'] ) ? $attributes['de_la'] : 'EUR';
		$catre = isset( $attributes['catre'] ) ? $attributes['catre'] : 'RON';

		return self::render_convertor(
			array(
				'de_la' => $de_la,
				'catre' => $catre,
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

		return self::render_curs(
			array(
				'valute'         => $valute,
				'stil'           => $stil,
				'arata_variatie' => $arata_variatie,
			)
		);
	}
}

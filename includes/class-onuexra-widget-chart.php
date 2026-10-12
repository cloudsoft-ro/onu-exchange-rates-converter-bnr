<?php
/**
 * Classic WordPress Widget for BNR Exchange Rates Chart.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

namespace OnuExra;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Widget_Chart
 */
class Widget_Chart extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'onuexra_widget_chart',
			__( 'BNR Exchange Rates Chart', 'curs-bnr-convertor-valutar' ),
			array(
				'description' => __( 'Display interactive historical exchange rates chart for BNR currencies.', 'curs-bnr-convertor-valutar' ),
			)
		);
	}

	/**
	 * Register the widget.
	 */
	public static function register() {
		add_action(
			'widgets_init',
			function() {
				register_widget( 'OnuExra\Widget_Chart' );
			}
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title );

		$valuta             = ! empty( $instance['valuta'] ) ? $instance['valuta'] : 'EUR';
		$perioada           = ! empty( $instance['perioada'] ) ? $instance['perioada'] : '1luna';
		$comutator_perioade = ! empty( $instance['comutator_perioade'] ) ? 'da' : 'nu';
		$comutator_valute   = ! empty( $instance['comutator_valute'] ) ? 'da' : 'nu';
		$valute             = ! empty( $instance['valute'] ) ? $instance['valute'] : 'EUR,USD,GBP,CHF';
		$inaltime           = ! empty( $instance['inaltime'] ) ? intval( $instance['inaltime'] ) : 260;
		$tema               = ! empty( $instance['tema'] ) ? $instance['tema'] : 'implicit';
		$densitate          = ! empty( $instance['densitate'] ) ? $instance['densitate'] : 'compact';

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$output = Shortcodes::render_grafic(
			array(
				'valuta'             => $valuta,
				'perioada'           => $perioada,
				'valute'             => $valute,
				'comutator_perioade' => $comutator_perioade,
				'comutator_valute'   => $comutator_valute,
				'inaltime'           => $inaltime,
				'tema'               => $tema,
				'densitate'          => $densitate,
			)
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$title              = isset( $instance['title'] ) ? $instance['title'] : '';
		$valuta             = isset( $instance['valuta'] ) ? $instance['valuta'] : 'EUR';
		$perioada           = isset( $instance['perioada'] ) ? $instance['perioada'] : '1luna';
		$comutator_perioade = isset( $instance['comutator_perioade'] ) ? (bool) $instance['comutator_perioade'] : true;
		$comutator_valute   = isset( $instance['comutator_valute'] ) ? (bool) $instance['comutator_valute'] : false;
		$valute             = isset( $instance['valute'] ) ? $instance['valute'] : 'EUR,USD,GBP,CHF';
		$inaltime           = isset( $instance['inaltime'] ) ? intval( $instance['inaltime'] ) : 260;
		$tema               = isset( $instance['tema'] ) ? $instance['tema'] : 'implicit';
		$densitate          = isset( $instance['densitate'] ) ? $instance['densitate'] : 'compact';

		$currencies = DB::get_active_currencies();
		if ( empty( $currencies ) ) {
			$currencies = array( 'EUR', 'USD', 'GBP', 'CHF' );
		}
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'curs-bnr-convertor-valutar' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'valuta' ) ); ?>"><?php esc_html_e( 'Default Currency:', 'curs-bnr-convertor-valutar' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'valuta' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'valuta' ) ); ?>">
				<?php foreach ( $currencies as $c ) : ?>
					<?php if ( 'RON' !== $c ) : ?>
						<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $valuta, $c ); ?>><?php echo esc_html( $c ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'perioada' ) ); ?>"><?php esc_html_e( 'Default Period:', 'curs-bnr-convertor-valutar' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'perioada' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'perioada' ) ); ?>">
				<option value="7zile" <?php selected( $perioada, '7zile' ); ?>><?php esc_html_e( 'Last 7 Days', 'curs-bnr-convertor-valutar' ); ?></option>
				<option value="1luna" <?php selected( $perioada, '1luna' ); ?>><?php esc_html_e( 'Last 1 Month', 'curs-bnr-convertor-valutar' ); ?></option>
				<option value="3luni" <?php selected( $perioada, '3luni' ); ?>><?php esc_html_e( 'Last 3 Months', 'curs-bnr-convertor-valutar' ); ?></option>
				<option value="1an" <?php selected( $perioada, '1an' ); ?>><?php esc_html_e( 'Last 1 Year', 'curs-bnr-convertor-valutar' ); ?></option>
				<option value="5ani" <?php selected( $perioada, '5ani' ); ?>><?php esc_html_e( 'Last 5 Years', 'curs-bnr-convertor-valutar' ); ?></option>
			</select>
		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $comutator_perioade ); ?> id="<?php echo esc_attr( $this->get_field_id( 'comutator_perioade' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'comutator_perioade' ) ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'comutator_perioade' ) ); ?>"><?php esc_html_e( 'Show period switcher (7d, 1m, 3m, 1y, 5y)', 'curs-bnr-convertor-valutar' ); ?></label>
		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $comutator_valute ); ?> id="<?php echo esc_attr( $this->get_field_id( 'comutator_valute' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'comutator_valute' ) ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'comutator_valute' ) ); ?>"><?php esc_html_e( 'Allow switching currency', 'curs-bnr-convertor-valutar' ); ?></label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'valute' ) ); ?>"><?php esc_html_e( 'Selectable currencies (comma separated):', 'curs-bnr-convertor-valutar' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'valute' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'valute' ) ); ?>" type="text" value="<?php echo esc_attr( $valute ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'inaltime' ) ); ?>"><?php esc_html_e( 'Chart Height (px):', 'curs-bnr-convertor-valutar' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'inaltime' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'inaltime' ) ); ?>" type="number" min="180" max="600" step="10" value="<?php echo esc_attr( $inaltime ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'tema' ) ); ?>"><?php esc_html_e( 'Theme:', 'curs-bnr-convertor-valutar' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'tema' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'tema' ) ); ?>">
				<option value="implicit" <?php selected( $tema, 'implicit' ); ?>><?php esc_html_e( 'Default (Global)', 'curs-bnr-convertor-valutar' ); ?></option>
				<option value="light" <?php selected( $tema, 'light' ); ?>><?php esc_html_e( 'Light', 'curs-bnr-convertor-valutar' ); ?></option>
				<option value="dark" <?php selected( $tema, 'dark' ); ?>><?php esc_html_e( 'Dark', 'curs-bnr-convertor-valutar' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'densitate' ) ); ?>"><?php esc_html_e( 'Density:', 'curs-bnr-convertor-valutar' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'densitate' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'densitate' ) ); ?>">
				<option value="compact" <?php selected( $densitate, 'compact' ); ?>><?php esc_html_e( 'Compact (Sidebar)', 'curs-bnr-convertor-valutar' ); ?></option>
				<option value="normal" <?php selected( $densitate, 'normal' ); ?>><?php esc_html_e( 'Normal', 'curs-bnr-convertor-valutar' ); ?></option>
				<option value="spacious" <?php selected( $densitate, 'spacious' ); ?>><?php esc_html_e( 'Spacious', 'curs-bnr-convertor-valutar' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                       = array();
		$instance['title']              = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['valuta']             = ( ! empty( $new_instance['valuta'] ) ) ? strtoupper( sanitize_text_field( $new_instance['valuta'] ) ) : 'EUR';
		$instance['perioada']           = ( ! empty( $new_instance['perioada'] ) ) ? DB::normalize_period( $new_instance['perioada'] ) : '1luna';
		$instance['comutator_perioade'] = ! empty( $new_instance['comutator_perioade'] );
		$instance['comutator_valute']   = ! empty( $new_instance['comutator_valute'] );
		$instance['valute']             = ( ! empty( $new_instance['valute'] ) ) ? sanitize_text_field( $new_instance['valute'] ) : 'EUR,USD,GBP,CHF';
		$instance['inaltime']           = ( ! empty( $new_instance['inaltime'] ) ) ? absint( $new_instance['inaltime'] ) : 260;
		$instance['tema']               = ( ! empty( $new_instance['tema'] ) ) ? sanitize_key( $new_instance['tema'] ) : 'implicit';
		$instance['densitate']          = ( ! empty( $new_instance['densitate'] ) ) ? sanitize_key( $new_instance['densitate'] ) : 'compact';

		return $instance;
	}
}

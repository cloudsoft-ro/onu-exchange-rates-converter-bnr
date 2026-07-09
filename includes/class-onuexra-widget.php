<?php
/**
 * Classic WordPress Widget for BNR Exchange Rates.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

namespace OnuExra;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Widget
 */
class Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'onuexra_widget',
			__( 'BNR Exchange Rates', 'curs-bnr-convertor-valutar' ),
			array(
				'description' => __( 'Display BNR exchange rates on your site.', 'curs-bnr-convertor-valutar' ),
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
				register_widget( 'OnuExra\Widget' );
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

		$valute         = ! empty( $instance['valute'] ) ? $instance['valute'] : 'EUR,USD,GBP,CHF';
		$stil           = ! empty( $instance['stil'] ) ? $instance['stil'] : 'tabel';
		$arata_variatie = ! empty( $instance['arata_variatie'] ) ? 'da' : 'nu';

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$output = Shortcodes::render_curs(
			array(
				'valute'         => $valute,
				'stil'           => $stil,
				'arata_variatie' => $arata_variatie,
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
		$title          = isset( $instance['title'] ) ? $instance['title'] : '';
		$valute         = isset( $instance['valute'] ) ? $instance['valute'] : 'EUR,USD,GBP,CHF';
		$stil           = isset( $instance['stil'] ) ? $instance['stil'] : 'tabel';
		$arata_variatie = isset( $instance['arata_variatie'] ) ? (bool) $instance['arata_variatie'] : true;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'curs-bnr-convertor-valutar' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'valute' ) ); ?>"><?php esc_html_e( 'Displayed currencies (comma separated):', 'curs-bnr-convertor-valutar' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'valute' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'valute' ) ); ?>" type="text" value="<?php echo esc_attr( $valute ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'stil' ) ); ?>"><?php esc_html_e( 'Style:', 'curs-bnr-convertor-valutar' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'stil' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'stil' ) ); ?>">
				<option value="tabel" <?php selected( $stil, 'tabel' ); ?>><?php esc_html_e( 'Table', 'curs-bnr-convertor-valutar' ); ?></option>
				<option value="card" <?php selected( $stil, 'card' ); ?>><?php esc_html_e( 'Cards', 'curs-bnr-convertor-valutar' ); ?></option>
				<option value="inline" <?php selected( $stil, 'inline' ); ?>><?php esc_html_e( 'Inline', 'curs-bnr-convertor-valutar' ); ?></option>
			</select>
		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $arata_variatie ); ?> id="<?php echo esc_attr( $this->get_field_id( 'arata_variatie' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'arata_variatie' ) ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'arata_variatie' ) ); ?>"><?php esc_html_e( 'Show variation from previous day', 'curs-bnr-convertor-valutar' ); ?></label>
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
		$instance                   = array();
		$instance['title']          = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['valute']         = ( ! empty( $new_instance['valute'] ) ) ? sanitize_text_field( $new_instance['valute'] ) : 'EUR,USD,GBP,CHF';
		$instance['stil']           = ( ! empty( $new_instance['stil'] ) ) ? sanitize_key( $new_instance['stil'] ) : 'tabel';
		$instance['arata_variatie'] = ! empty( $new_instance['arata_variatie'] );

		return $instance;
	}
}

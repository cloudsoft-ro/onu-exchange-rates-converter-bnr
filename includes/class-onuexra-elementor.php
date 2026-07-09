<?php
/**
 * Elementor Page Builder Widget Integration.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

namespace OnuExra;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Elementor
 */
class Elementor {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Register the Elementor widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		$widgets_manager->register( new Elementor_Widget() );
	}
}

/**
 * Class Elementor_Widget
 */
class Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name.
	 */
	public function get_name() {
		return 'onuexra_rates_widget';
	}

	/**
	 * Get widget title.
	 */
	public function get_title() {
		return __( 'BNR Exchange Rates', 'curs-bnr-convertor-valutar' );
	}

	/**
	 * Get widget icon.
	 */
	public function get_icon() {
		return 'eicon-exchange';
	}

	/**
	 * Get widget categories.
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Register control settings in Elementor inspector.
	 */
	protected function register_controls() {
		$db_currencies = DB::get_active_currencies();
		if ( empty( $db_currencies ) ) {
			$db_currencies = array( 'EUR', 'USD', 'GBP', 'CHF' );
		}
		$options = array();
		foreach ( $db_currencies as $currency ) {
			if ( 'RON' !== $currency ) {
				$options[ $currency ] = $currency;
			}
		}

		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'BNR Exchange Rates Settings', 'curs-bnr-convertor-valutar' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'valute',
			array(
				'label'       => __( 'Displayed currencies', 'curs-bnr-convertor-valutar' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $options,
				'default'     => array( 'EUR', 'USD', 'GBP', 'CHF' ),
				'description' => __( 'Select the currencies you want to display in the list.', 'curs-bnr-convertor-valutar' ),
			)
		);

		$this->add_control(
			'stil',
			array(
				'label'   => __( 'Display style', 'curs-bnr-convertor-valutar' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'tabel'  => __( 'Table', 'curs-bnr-convertor-valutar' ),
					'card'   => __( 'Cards', 'curs-bnr-convertor-valutar' ),
					'inline' => __( 'Inline', 'curs-bnr-convertor-valutar' ),
				),
				'default' => 'tabel',
			)
		);

		$this->add_control(
			'arata_variatie',
			array(
				'label'        => __( 'Show variation', 'curs-bnr-convertor-valutar' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'curs-bnr-convertor-valutar' ),
				'label_off'    => __( 'No', 'curs-bnr-convertor-valutar' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget on frontend.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$valute_arr = isset( $settings['valute'] ) ? $settings['valute'] : array( 'EUR', 'USD', 'GBP', 'CHF' );
		$valute     = is_array( $valute_arr ) ? implode( ',', $valute_arr ) : $valute_arr;

		$stil           = isset( $settings['stil'] ) ? $settings['stil'] : 'tabel';
		$arata_variatie = ( 'yes' === $settings['arata_variatie'] ) ? 'da' : 'nu';

		wp_enqueue_style( 'onuexra-style' );

		$output = Shortcodes::render_curs(
			array(
				'valute'         => $valute,
				'stil'           => $stil,
				'arata_variatie' => $arata_variatie,
			)
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}
}

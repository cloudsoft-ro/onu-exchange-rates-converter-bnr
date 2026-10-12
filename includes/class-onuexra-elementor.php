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
		$widgets_manager->register( new Elementor_Chart_Widget() );
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

/**
 * Class Elementor_Chart_Widget
 */
class Elementor_Chart_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name.
	 */
	public function get_name() {
		return 'onuexra_chart_widget';
	}

	/**
	 * Get widget title.
	 */
	public function get_title() {
		return __( 'BNR Exchange Rates Chart', 'curs-bnr-convertor-valutar' );
	}

	/**
	 * Get widget icon.
	 */
	public function get_icon() {
		return 'eicon-history';
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
			'section_chart_content',
			array(
				'label' => __( 'Chart Settings', 'curs-bnr-convertor-valutar' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'valuta',
			array(
				'label'   => __( 'Default currency', 'curs-bnr-convertor-valutar' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'default' => 'EUR',
			)
		);

		$this->add_control(
			'perioada',
			array(
				'label'   => __( 'Default period', 'curs-bnr-convertor-valutar' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'7zile' => __( 'Last 7 Days', 'curs-bnr-convertor-valutar' ),
					'1luna' => __( 'Last 1 Month', 'curs-bnr-convertor-valutar' ),
					'3luni' => __( 'Last 3 Months', 'curs-bnr-convertor-valutar' ),
					'1an'   => __( 'Last 1 Year', 'curs-bnr-convertor-valutar' ),
					'5ani'  => __( 'Last 5 Years', 'curs-bnr-convertor-valutar' ),
				),
				'default' => '1luna',
			)
		);

		$this->add_control(
			'comutator_perioade',
			array(
				'label'        => __( 'Show period switcher buttons', 'curs-bnr-convertor-valutar' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'curs-bnr-convertor-valutar' ),
				'label_off'    => __( 'No', 'curs-bnr-convertor-valutar' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'comutator_valute',
			array(
				'label'        => __( 'Allow switching currency', 'curs-bnr-convertor-valutar' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'curs-bnr-convertor-valutar' ),
				'label_off'    => __( 'No', 'curs-bnr-convertor-valutar' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'valute',
			array(
				'label'       => __( 'Allowed currencies', 'curs-bnr-convertor-valutar' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $options,
				'default'     => array( 'EUR', 'USD', 'GBP', 'CHF' ),
				'condition'   => array(
					'comutator_valute' => 'yes',
				),
			)
		);

		$this->add_control(
			'titlu',
			array(
				'label'       => __( 'Custom title', 'curs-bnr-convertor-valutar' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'E.g. Evoluție Curs BNR', 'curs-bnr-convertor-valutar' ),
			)
		);

		$this->add_control(
			'inaltime',
			array(
				'label'   => __( 'Chart height (px)', 'curs-bnr-convertor-valutar' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 180,
				'max'     => 600,
				'step'    => 10,
				'default' => 300,
			)
		);

		$this->add_control(
			'tema',
			array(
				'label'   => __( 'Theme', 'curs-bnr-convertor-valutar' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'implicit' => __( 'Default (Global)', 'curs-bnr-convertor-valutar' ),
					'light'    => __( 'Light', 'curs-bnr-convertor-valutar' ),
					'dark'     => __( 'Dark', 'curs-bnr-convertor-valutar' ),
				),
				'default' => 'implicit',
			)
		);

		$this->add_control(
			'densitate',
			array(
				'label'   => __( 'Density', 'curs-bnr-convertor-valutar' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'implicit' => __( 'Default (Global)', 'curs-bnr-convertor-valutar' ),
					'compact'  => __( 'Compact', 'curs-bnr-convertor-valutar' ),
					'normal'   => __( 'Normal', 'curs-bnr-convertor-valutar' ),
					'spacious' => __( 'Spacious', 'curs-bnr-convertor-valutar' ),
				),
				'default' => 'implicit',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget on frontend.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$valuta             = isset( $settings['valuta'] ) ? $settings['valuta'] : 'EUR';
		$perioada           = isset( $settings['perioada'] ) ? $settings['perioada'] : '1luna';
		$comutator_perioade = ( 'yes' === $settings['comutator_perioade'] ) ? 'da' : 'nu';
		$comutator_valute   = ( 'yes' === $settings['comutator_valute'] ) ? 'da' : 'nu';

		$valute_arr = isset( $settings['valute'] ) ? $settings['valute'] : array( 'EUR', 'USD', 'GBP', 'CHF' );
		$valute     = is_array( $valute_arr ) ? implode( ',', $valute_arr ) : $valute_arr;

		$titlu     = isset( $settings['titlu'] ) ? $settings['titlu'] : '';
		$inaltime  = isset( $settings['inaltime'] ) ? intval( $settings['inaltime'] ) : 300;
		$tema      = isset( $settings['tema'] ) ? $settings['tema'] : 'implicit';
		$densitate = isset( $settings['densitate'] ) ? $settings['densitate'] : 'implicit';

		wp_enqueue_style( 'onuexra-style' );
		wp_enqueue_script( 'onuexra-chart' );

		$output = Shortcodes::render_grafic(
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
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}
}

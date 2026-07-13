<?php
/**
 * Elementor Widget — LDAP Staff Directory.
 *
 * @package LDAP_Staff_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDAP_ED_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'ldap_staff_directory';
	}

	public function get_title() {
		return __( 'LDAP Staff Directory', 'ldap-staff-directory' );
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'ldap', 'directory', 'employees', 'staff' );
	}

	// ── Controls ─────────────────────────────────────────────────────────────

	protected function register_controls() {

		// ── Content tab ──────────────────────────────────────────────────────
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'ldap-staff-directory' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'fields',
			array(
				'label'    => __( 'Fields to Display', 'ldap-staff-directory' ),
				'type'     => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options'  => array(
					'name'       => __( 'Full Name', 'ldap-staff-directory' ),
					'email'      => __( 'Email', 'ldap-staff-directory' ),
					'title'      => __( 'Job Title', 'ldap-staff-directory' ),
					'department' => __( 'Department', 'ldap-staff-directory' ),
					'phone'      => __( 'Phone', 'ldap-staff-directory' ),
					'extension'  => __( 'Extension', 'ldap-staff-directory' ),
				),
				'default' => array( 'name', 'email', 'title', 'department' ),
			)
		);

		$this->add_control(
			'per_page',
			array(
				'label'   => __( 'Items per Page', 'ldap-staff-directory' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 20,
				'min'     => 1,
				'max'     => 500,
			)
		);

		$this->add_control(
			'enable_search',
			array(
				'label'        => __( 'Enable Search Bar', 'ldap-staff-directory' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'ldap-staff-directory' ),
				'label_off'    => __( 'No', 'ldap-staff-directory' ),
				'return_value' => 'true',
				'default'      => 'true',
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'ldap-staff-directory' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'default' => '3',
			)
		);

		$this->end_controls_section();

		// ── Style tab ────────────────────────────────────────────────────────
		$this->start_controls_section(
			'section_style_card',
			array(
				'label' => __( 'Cards', 'ldap-staff-directory' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'primary_color',
			array(
				'label'     => __( 'Primary Color', 'ldap-staff-directory' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#0073aa',
				'selectors' => array(
					'{{WRAPPER}} .ldap-directory-wrap' => '--ldap-primary-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'card_bg',
			array(
				'label'     => __( 'Card Background', 'ldap-staff-directory' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .ldap-directory-wrap' => '--ldap-card-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Text Color', 'ldap-staff-directory' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#3c434a',
				'selectors' => array(
					'{{WRAPPER}} .ldap-directory-wrap' => '--ldap-text-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'card_typography',
				'label'    => __( 'Typography', 'ldap-staff-directory' ),
				'selector' => '{{WRAPPER}} .ldap-staff-card',
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Card Padding', 'ldap-staff-directory' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ldap-staff-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_border_radius',
			array(
				'label'      => __( 'Border Radius', 'ldap-staff-directory' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 6 ),
				'selectors'  => array(
					'{{WRAPPER}} .ldap-directory-wrap' => '--ldap-card-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'grid_gap',
			array(
				'label'      => __( 'Cards Gap', 'ldap-staff-directory' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 20 ),
				'selectors'  => array(
					'{{WRAPPER}} .ldap-directory-wrap' => '--ldap-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	// ── Render ────────────────────────────────────────────────────────────────

	protected function render() {
		$settings = $this->get_settings_for_display();
		$fields   = is_array( $settings['fields'] ) ? implode( ',', $settings['fields'] ) : $settings['fields'];
		$columns  = absint( $settings['columns'] ?? 3 );
		$search   = ( 'true' === $settings['enable_search'] ) ? 'true' : 'false';
		$per_page = absint( $settings['per_page'] ?? 20 );

		// Inject column variable on the widget wrapper — cascades to .ldap-directory-wrap inside.
		$this->add_render_attribute( '_wrapper', 'style', '--ldap-columns:' . absint( $columns ) . ';' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo do_shortcode(
			sprintf(
				'[ldap_directory search="%1$s" per_page="%2$d" fields="%3$s"]',
				esc_attr( $search ),
				$per_page,
				esc_attr( $fields )
			)
		);
	}

	protected function content_template() {
		// Live preview not available for server-side rendering.
		echo '<div class="elementor-alert elementor-alert-info">' . esc_html__( 'LDAP Directory — preview available on the frontend.', 'ldap-staff-directory' ) . '</div>';
	}
}

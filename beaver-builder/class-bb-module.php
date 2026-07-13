<?php
/**
 * Beaver Builder Module — LDAP Staff Directory.
 *
 * @package LDAP_Staff_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only register when FL Builder is active.
if ( ! class_exists( 'FLBuilder' ) ) {
	return;
}

FLBuilder::register_module(
	'LDAP_ED_BB_Module',
	array(

		// ── General tab ──────────────────────────────────────────────────────
		'general' => array(
			'title'    => __( 'General', 'ldap-staff-directory' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(

						'fields_to_show' => array(
							'type'    => 'select',
							'label'   => __( 'Fields to Display', 'ldap-staff-directory' ),
							'multi-select' => true,
							'default' => array( 'name', 'email', 'title', 'department' ),
							'options' => array(
								'name'       => __( 'Full Name', 'ldap-staff-directory' ),
								'email'      => __( 'Email', 'ldap-staff-directory' ),
								'title'      => __( 'Job Title', 'ldap-staff-directory' ),
								'department' => __( 'Department', 'ldap-staff-directory' ),
								'phone'      => __( 'Phone', 'ldap-staff-directory' ),
								'extension'  => __( 'Extension', 'ldap-staff-directory' ),
							),
						),

						'per_page' => array(
							'type'    => 'unit',
							'label'   => __( 'Items per Page', 'ldap-staff-directory' ),
							'default' => '20',
							'units'   => array( '' ),
						),

						'enable_search' => array(
							'type'    => 'select',
							'label'   => __( 'Enable Search Bar', 'ldap-staff-directory' ),
							'default' => 'true',
							'options' => array(
								'true'  => __( 'Yes', 'ldap-staff-directory' ),
								'false' => __( 'No', 'ldap-staff-directory' ),
							),
						),

						'columns' => array(
							'type'    => 'select',
							'label'   => __( 'Columns', 'ldap-staff-directory' ),
							'default' => '3',
							'options' => array(
								'1' => '1',
								'2' => '2',
								'3' => '3',
								'4' => '4',
							),
						),

					),
				),
			),
		),

		// ── Style tab ────────────────────────────────────────────────────────
		'style' => array(
			'title'    => __( 'Style', 'ldap-staff-directory' ),
			'sections' => array(
				'colors' => array(
					'title'  => __( 'Colors', 'ldap-staff-directory' ),
					'fields' => array(

						'primary_color' => array(
							'type'    => 'color',
							'label'   => __( 'Primary Color', 'ldap-staff-directory' ),
							'default' => '0073aa',
						),

						'card_bg_color' => array(
							'type'    => 'color',
							'label'   => __( 'Card Background', 'ldap-staff-directory' ),
							'default' => 'ffffff',
						),

						'text_color' => array(
							'type'    => 'color',
							'label'   => __( 'Text Color', 'ldap-staff-directory' ),
							'default' => '3c434a',
						),

					),
				),

				'typography' => array(
					'title'  => __( 'Typography', 'ldap-staff-directory' ),
					'fields' => array(
						'font_size' => array(
							'type'    => 'unit',
							'label'   => __( 'Font Size (px)', 'ldap-staff-directory' ),
							'default' => '14',
							'units'   => array( 'px' ),
						),
					),
				),

				'layout' => array(
					'title'  => __( 'Layout', 'ldap-staff-directory' ),
					'fields' => array(
						'gap' => array(
							'type'    => 'unit',
							'label'   => __( 'Cards Gap (px)', 'ldap-staff-directory' ),
							'default' => '20',
							'units'   => array( 'px' ),
						),
						'border_radius' => array(
							'type'    => 'unit',
							'label'   => __( 'Border Radius (px)', 'ldap-staff-directory' ),
							'default' => '6',
							'units'   => array( 'px' ),
						),
					),
				),
			),
		),

	)
);

class LDAP_ED_BB_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'LDAP Staff Directory', 'ldap-staff-directory' ),
				'description'     => __( 'Displays an employee directory from an LDAP/LDAPS server.', 'ldap-staff-directory' ),
				'group'           => __( 'General', 'ldap-staff-directory' ),
				'category'        => __( 'Basic', 'ldap-staff-directory' ),
				'dir'             => LDAP_ED_DIR . 'beaver-builder/',
				'url'             => LDAP_ED_URL . 'beaver-builder/',
				'icon'            => 'button.svg',
				'partial_refresh' => true,
			)
		);
	}

	/**
	 * Enqueue per-instance CSS via wp_add_inline_style() during wp_enqueue_scripts.
	 * This replaces the inline <style> block previously output in frontend.php.
	 */
	public function enqueue_scripts() {
		// Ensure the public stylesheet handle is registered before attaching inline CSS.
		if ( ! wp_style_is( 'ldap-ed-public', 'registered' ) ) {
			wp_register_style(
				'ldap-ed-public',
				LDAP_ED_URL . 'public/css/directory.css',
				array(),
				LDAP_ED_VERSION
			);
		}

		$settings      = $this->settings;
		$uid           = $this->node;
		$primary_color = ! empty( $settings->primary_color ) ? '#' . ltrim( $settings->primary_color, '#' ) : '#0073aa';
		$card_bg       = ! empty( $settings->card_bg_color )  ? '#' . ltrim( $settings->card_bg_color, '#' )  : '#ffffff';
		$text_color    = ! empty( $settings->text_color )     ? '#' . ltrim( $settings->text_color, '#' )     : '#3c434a';
		$font_size     = absint( $settings->font_size ?? 14 );
		$gap           = absint( $settings->gap ?? 20 );
		$border_radius = absint( $settings->border_radius ?? 6 );
		$columns       = absint( $settings->columns ?? 3 );

		$css  = '.fl-node-' . esc_attr( $uid ) . ' .ldap-directory-wrap{';
		$css .= '--ldap-primary-color:' . esc_attr( $primary_color ) . ';';
		$css .= '--ldap-card-bg:' . esc_attr( $card_bg ) . ';';
		$css .= '--ldap-text-color:' . esc_attr( $text_color ) . ';';
		$css .= '--ldap-font-size:' . absint( $font_size ) . 'px;';
		$css .= '--ldap-gap:' . absint( $gap ) . 'px;';
		$css .= '--ldap-card-radius:' . absint( $border_radius ) . 'px;';
		$css .= '--ldap-columns:' . absint( $columns ) . ';';
		$css .= '}';

		wp_add_inline_style( 'ldap-ed-public', $css );
	}
}

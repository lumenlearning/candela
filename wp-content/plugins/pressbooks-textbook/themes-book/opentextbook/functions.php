<?php
/**
 * @author  PressBooks <code@pressbooks.org>
 * @license GPLv2 (or any later version)
 */

/* ------------------------------------------------------------------------ *
 * Google Webfonts
 * ------------------------------------------------------------------------ */

function fitzgerald_enqueue_styles() {
	wp_enqueue_style( 'fitzgerald-fonts', 'http://fonts.googleapis.com/css?family=Crimson+Text:400,400italic,700|Roboto+Condensed:400,300,300italic,400italic' );
}
add_action( 'wp_print_styles', 'fitzgerald_enqueue_styles' );

/* ------------------------------------------------------------------------ *
 * Global Options Tab
 * ------------------------------------------------------------------------ */

// Global Options Registration
function opentextbook_theme_options_global_init() {

	$_page = 'pressbooks_theme_options_global';
	$_option = 'opentextbook_theme_options_global';
	$_section = 'global_options_section';
	$defaults = array(
		'toc_collapse' => 0
	);

	if ( false == get_option( $_option ) ) {
		add_option( $_option, $defaults );
	}

	add_settings_field(
		'toc_collapse',
		__( 'Table of Contents', 'opentextbook' ),
		'opentextbook_theme_toc_collapse_callback',
		$_page,
		$_section,
		array(
			 __( 'Make Table of Contents Collapsible', 'opentextbook' )
		)
	);
	
	add_settings_field(
		'source_based_css',
		__( 'Load source-based styles', 'opentextbook' ),
		'opentextbook_theme_source_based_css_callback',
		$_page,
		$_section,
		array(
			 __( 'OpenStax', 'opentextbook' ),
			 __( 'Lardbucket', 'opentextbook' )
		)
	);
	
	register_setting(
		$_page,
		$_option,
		'opentextbook_theme_options_global_sanitize'
	);
}
add_action('admin_init', 'opentextbook_theme_options_global_init');


// TOC Options Field Callback
function opentextbook_theme_toc_collapse_callback( $args ) {

	$options = get_option( 'opentextbook_theme_options_global' );
	
	if ( ! isset( $options['toc_collapse'] ) ) {
		$options['toc_collapse'] = 0;
	}

	$html = '<input type="checkbox" id="toc_collapse" name="opentextbook_theme_options_global[toc_collapse]" value="1" ' . checked( 1, $options['toc_collapse'], false ) . '/>';
	$html .= '<label for="toc_collapse"> ' . $args[0] . '</label>';
	echo $html;
}

// Source based CSS Field Callback
function opentextbook_theme_source_based_css_callback( $args ) {

	$options = get_option( 'opentextbook_theme_options_global' );
	
	if ( ! isset( $options['source_based_css'] ) ) {
		$options['source_based_css'] = 0;
	}
	$html .= '<input type="checkbox" id="source_based_css_1" name="opentextbook_theme_options_global[source_based_css_1]" value="1" '.checked(1, $options['source_based_css']&1, false) . '/>';
	$html .= '<label for="source_based_css_1">'.$args[0].'</label><br/>';
	$html .= '<input type="checkbox" id="source_based_css_2" name="opentextbook_theme_options_global[source_based_css_2]" value="2" '.checked(2, $options['source_based_css']&2, false) . '/>';
	$html .= '<label for="source_based_css_2">'.$args[1].'</label>';
	
	echo $html;
}

// Global Options Input Sanitization
function opentextbook_theme_options_global_sanitize( $input ) {

	$options = get_option( 'opentextbook_theme_options_global' );

	if ( ! isset( $input['toc_collapse'] ) || $input['toc_collapse'] != '1' ) {
		$options['toc_collapse'] = 0;
	} else {
		$options['toc_collapse'] = 1;
	}
	$options['source_based_css'] = 0;
	if ( isset( $input['source_based_css_1'] ) && $input['source_based_css_1']=='1' ) {
		$options['source_based_css'] += 1;
	} 
	if ( isset( $input['source_based_css_2'] ) && $input['source_based_css_2']=='2' ) {
		$options['source_based_css'] += 2;
	} 
	return $options;
}


/**
 * Get any header scripts, based on options
 *
 * @return string
 *
 */
function opentextbook_get_header_scripts() {
	$options = get_option( 'opentextbook_theme_options_global' );
	if ( @$options['toc_collapse'] ) {
		wp_enqueue_script(
			'opentextbook_toc_collapse', 
			get_stylesheet_directory_uri().'/js/toc_collapse.js',
			array( 'jquery' ));
		wp_enqueue_style( 'dashicons' );
	}
	if (@$options['source_based_css']&1==1) {
		wp_enqueue_style('opentextbook_openstax_css', 
			get_stylesheet_directory_uri().'/css/openstax.css');
		wp_enqueue_script(
			'opentextbook_openstax_js', 
			get_stylesheet_directory_uri().'/js/openstax.js',
			array( 'jquery' ));
	}
	if (@$options['source_based_css']&2==2) {
		wp_enqueue_style('opentextbook_lb_css', 
			get_stylesheet_directory_uri().'/css/lb.css');
	}
}
add_action('wp_enqueue_scripts', 'opentextbook_get_header_scripts');

?>

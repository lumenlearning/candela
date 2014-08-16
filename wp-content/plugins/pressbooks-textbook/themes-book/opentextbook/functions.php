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
		__( 'Table of Contents Collapse', 'opentextbook' ),
		'opentextbook_theme_toc_collapse_callback',
		$_page,
		$_section,
		array(
			 __( 'Make Table of Contents Collapseable', 'opentextbook' )
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
	$html .= '<br/><i>Not recommended if you are putting Part Text in the parts</i>';
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
}
add_action('wp_enqueue_scripts', 'opentextbook_get_header_scripts');

?>

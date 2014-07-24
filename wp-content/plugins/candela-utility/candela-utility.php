<?php

/**
 * Candela Utility
 * @wordpress-plugin
 * Plugin Name:       Candela Utility
 * Description:       Candela helper plugin to manage additional config and bootstrapping.
 * Version:           0.1
 * Author:            Lumen Learning
 * Author URI:        http://lumenlearning.com
 * Text Domain:       lumen
 * License:           GPLv2 or later
 * GitHub Plugin URI: https://github.com/lumenlearning/candela
 */

namespace Candela\Utility;

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

init();

const VERSION = '0.1';

function init() {
	add_action( 'init', '\Candela\Utility\register_theme' );
	add_action( 'wp_enqueue_style', '\Candela\Utility\register_child_theme' );
	add_filter( 'allowed_themes', '\Candela\Utility\add_theme', 12 );
	add_action( 'admin_menu', '\Candela\Utility\adjust_admin_menu', 11);
	add_action( 'plugins_loaded', '\Candela\Utility\remove_pressbooks_branding' );

	add_filter( 'admin_footer_text', '\Candela\Utility\add_footer_link' );
	add_action( 'admin_bar_menu', '\Candela\Utility\replace_menu_bar_branding', 11 );
}

function remove_pressbooks_branding() {
	remove_action( 'admin_head', '\PressBooks\Admin\Laf\add_feedback_dialogue' );
	remove_filter( 'admin_footer_text', '\PressBooks\Admin\Laf\add_footer_link' );
	remove_action( 'admin_bar_menu', '\PressBooks\Admin\Laf\replace_menu_bar_branding', 11 );
}

function register_theme() {
	register_theme_directory( __DIR__ . '/themes' );
	wp_register_style( 'candela', __DIR__ . '/themes/candela/style.css', array( 'pressbooks' ), VERSION, 'screen' );
}

function register_child_theme() {
	wp_enqueue_style( 'candela' );
}

function add_theme( $themes ) {
	$merge_themes = array();

	if ( \Pressbooks\Book::isBook() ) {
		$registered_themes = search_theme_directories();
		foreach ( $registered_themes as $key => $val ) {
			if ( $val['theme_root'] == __DIR__ . '/themes' ) {
				$merge_themes[$key] = 1;
			}
		}
		// add our theme
		$themes = array_merge( $themes, $merge_themes );
	}
	return $themes;
}

function adjust_admin_menu() {
	global $blog_id;

	$current_user = wp_get_current_user();

	if ( $blog_id != 1 ) {
		remove_menu_page( "edit.php?post_type=lti_consumer" );
	}

	// Remove items that non-admins should not see
	if ( ! in_array('administrator', $current_user->roles) ) {
		remove_menu_page('themes.php');
		remove_menu_page('pb_export');
		remove_menu_page('pb_import');
		remove_submenu_page('options-general.php', 'pb_import');
		remove_menu_page('lti-maps');
		remove_menu_page('edit-comments.php');
	}

	// Remove items for non-admins and non-editors
	if ( ! ( in_array('administrator' , $current_user->roles ) || in_array('editor', $current_user->roles) ) ) {
		$metadata = new \PressBooks\Metadata();
		$meta = $metadata->getMetaPost();
		if ( ! empty( $meta ) ) {
			$book_info_url = 'post.php?post=' . absint( $meta->ID ) . '&action=edit';
		} else {
			$book_info_url = 'post-new.php?post_type=metadata';
		}
		remove_menu_page($book_info_url);
	}

}

/*
 * Replace logo in menu bar and add links to About page, Contact page, and forums
 *
 * @param \WP_Admin_Bar $wp_admin_bar The admin bar object as it currently exists
 */
function replace_menu_bar_branding( $wp_admin_bar ) {

	// remove wordpress menus
	$wp_admin_bar->remove_menu( 'wp-logo' );
	$wp_admin_bar->remove_menu( 'documentation' );
	$wp_admin_bar->remove_menu( 'feedback' );
	$wp_admin_bar->remove_menu( 'wporg' );
	$wp_admin_bar->remove_menu( 'about' );

	// remove pressbooks menus
	$wp_admin_bar->remove_menu( 'support-forums' );
	$wp_admin_bar->remove_menu( 'contact' );

	$wp_admin_bar->add_menu( array(
		'id' => 'wp-logo',
		'title' => 'Candela',
		'href' => ( 'http://lumenlearning.com/' ),
		'meta' => array(
			'title' => __( 'About LumenLearning', 'lumen' ),
		),
	) );

}


/**
 * Add a custom message in admin footer
 */
function add_footer_link() {

	printf(
		'<p id="footer-left" class="alignleft">
		<span id="footer-thankyou">%s <a href="http://lumenlearning.com">Candela</a>
		</span>
		</p>',
		__( 'Powered by', 'lumen' )
	);

}

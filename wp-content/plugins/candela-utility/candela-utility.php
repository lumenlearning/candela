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
	add_action( 'init', '\Candela\Utility\wp_init' );
	add_action( 'wp_enqueue_style', '\Candela\Utility\register_child_theme' );
	add_filter( 'allowed_themes', '\Candela\Utility\add_theme', 12 );
	add_filter( 'gettext', '\Candela\Utility\gettext', 20, 3 );
	add_filter( 'gettext_with_context', '\Candela\Utility\gettext_with_context', 20, 4 );
	add_action( 'admin_menu', '\Candela\Utility\adjust_admin_menu', 11);
	add_action( 'plugins_loaded', '\Candela\Utility\remove_pressbooks_branding' );
	add_action( 'pressbooks_new_blog', '\Candela\Utility\pressbooks_new_blog' );
	add_action( 'wp_insert_post', '\Candela\Utility\pressbooks_new_book_info' );
	add_action( 'custom_metadata_manager_init_metadata', '\Candela\Utility\add_meta_boxes' );


	add_filter( 'admin_footer_text', '\Candela\Utility\add_footer_link' );
	add_action( 'admin_bar_menu', '\Candela\Utility\replace_menu_bar_branding', 11 );

	add_filter( 'embed_oembed_html', '\Candela\Utility\embed_oembed_html', 10, 3 );
}

function gettext( $translated_text, $text, $domain ) {
	if ( $domain == 'pressbooks' ) {
		$translations = array(
			"Chapter Metadata" => "Page Metadata",
			"Chapter Short Title (appears in the PDF running header)" => "Page Short Title (appears in the PDF running header)",
			"Chapter Subtitle (appears in the Web/ebook/PDF output)" => "Page Subtitle (appears in the Web/ebook/PDF output)",
			"Chapter Author (appears in Web/ebook/PDF output)" => "Page Author (appears in Web/ebook/PDF output)",
			"Promote your book, set individual chapters privacy below." => "Promote your book, set individual page's privacy below.",
			"Add Chapter" => "Add Page",
			"Reordering the Chapters" => "Reordering the Pages",
			"Chapter 1" => "Page 1",
			"Imported %s chapters." => "Imported %s pages.",
			"Chapters" => "Pages",
			"Chapter" => "Page",
			"Add New Chapter" => "Add New Page",
			"Edit Chapter" => "Edit Page",
			"New Chapter" => "New Page",
			"View Chapter" => "View Page",
			"Search Chapters" => "Search Pages",
			"No chapters found" => "No pages found",
			"No chapters found in Trash" => "No pages found in Trash",
			"Chapter numbers" => "Page numbers",
			"display chapter numbers" => "display page numbers",
			"do not display chapter numbers" => "do not display page numbers",
			"Chapter Numbers" => "Page Numbers",
			"Display chapter numbers" => "Display page numbers",
			"This is the first chapter in the main body of the text. You can change the " => "This is the first page in the main body of the text. You can change the ",
			"text, rename the chapter, add new chapters, and add new parts." => "text, rename the page, add new pages, and add new parts.",
			"Only users you invite can see your book, regardless of individual chapter " => "Only users you invite can see your book, regardless of individual page ",
		);
		if (isset($translations[$translated_text])) {
			$translated_text = $translations[$translated_text];
		}
	}
	return $translated_text;
}

function gettext_with_context( $translated_text, $text, $context, $domain ) {
	if ( $domain == 'pressbooks' ) {
		$translated_text = gettext($translated_text, $text, $domain);
	}
	return $translated_text;
}

function remove_pressbooks_branding() {
	remove_action( 'admin_head', '\PressBooks\Admin\Laf\add_feedback_dialogue' );
	remove_filter( 'admin_footer_text', '\PressBooks\Admin\Laf\add_footer_link' );
	remove_action( 'admin_bar_menu', '\PressBooks\Admin\Laf\replace_menu_bar_branding', 11 );
}

function wp_init() {
	register_theme();
	register_oembed_providers();
}

function register_theme() {
	register_theme_directory( __DIR__ . '/themes' );
	wp_register_style( 'candela', __DIR__ . '/themes/candela/style.css', array( 'pressbooks' ), VERSION, 'screen' );
	wp_register_style( 'candela-clean', __DIR__ . '/themes/candela-clean/style.css', array( 'pressbooks' ), VERSION, 'screen' );
}

function register_child_theme() {
	wp_enqueue_style( 'candela' );
	wp_enqueue_style( 'candela-clean' );
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

  add_submenu_page('pb_export', 'Export to Thin-CC', 'Thin-CC Export', 'export', 'tools.php?page=candela-thin-export.php');

	// Remove items that non-admins should not see
	if ( ! ( in_array('administrator', $current_user->roles) || is_super_admin() ) ) {
		remove_menu_page('themes.php');
		remove_menu_page('pb_export');
		remove_menu_page('pb_import');
		remove_menu_page('pb_sell');
		remove_submenu_page('options-general.php', 'pb_import');
		remove_menu_page('lti-maps');
		remove_menu_page('edit-comments.php');
	}

	// Remove items for non-admins and non-editors
	if ( ! ( in_array('administrator' , $current_user->roles ) || in_array('editor', $current_user->roles) || is_super_admin() ) ) {
		$metadata = new \PressBooks\Metadata();
		$meta = $metadata->getMetaPost();
		if ( ! empty( $meta ) ) {
			$book_info_url = 'post.php?post=' . absint( $meta->ID ) . '&action=edit';
		} else {
			$book_info_url = 'post-new.php?post_type=metadata';
		}
		remove_menu_page($book_info_url);
    remove_submenu_page('pb_export', 'tools.php?page=candela-thin-export.php');
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

/**
 * Filter embed_oembed_html.
 *
 * Replace all 'http://' links with 'https://
 */
function embed_oembed_html($html, $url, $attr) {
	if ( is_ssl() ) {
		return str_replace('http://', 'https://', $html);
	}

	return $html;
}

/**
 * Add any new oembed_providers (This is currently a workaround for https://github.com/tatemae/oea/issues/44)
 */
function register_oembed_providers() {
	$providers = array(
		'openassessments.com' => array(
			'regex' => '#https?://(openassessments\.com)/assessments/(.*)#i',
		),
		'openassessments.org' => array(
			'regex' => '#https?://(openassessments\.org)/assessments/(.*)#i',
		),
		'wwwopenassessments.com' => array(
			'regex' => '#https?://(www\.openassessments\.com)/assessments/(.*)#i',
		),
		'wwwopenassessments.org' => array(
			'regex' => '#https?://(www.\openassessments\.org)/assessments/(.*)#i',
		),
		'oea.herokuapp.com' => array(
			'regex' => '#https?://(oea\.herokuapp\.com)/assessments/(.*)#i',
		),
	);

  wp_embed_register_handler( 'assessments.lumenlearning.com',
      '#https?://assessments\.lumenlearning\.com/assessments/(.*)#i',
      '\Candela\Utility\lumen_asmnt_embed_handler' );

	foreach ($providers as $id => $info ) {
		wp_embed_register_handler( $id, $info['regex'], '\Candela\Utility\embed_handler' );
	}
}

/**
 * Handle embeds
 */
function embed_handler( $matches, $attr, $url, $rawattr ) {
	// Use the current post as the external id
	$permalink = get_permalink();
	if ( empty( $permalink ) ) {
		$permalink = get_bloginfo( 'url' );
	}

	$parameters = array(
		'confidence_levels=true',
		'enable_start=true',
		'eid=' . esc_url($permalink),
	);

	$parms = implode('&', $parameters);

	$embed = sprintf( '<iframe src="//%s/assessments/load?src_url=https://%s/api/assessments/%d.xml&results_end_point=https://%s/api&assessment_id=%d&%s" frameborder="0" style="border:none;width:100%%;height:100%%;min-height:400px;"></iframe>',
		esc_attr($matches[1]),
		esc_attr($matches[1]),
		esc_attr($matches[2]),
		esc_attr($matches[1]),
		esc_attr($matches[2]),
		$parms
	);

	return apply_filters( 'embed_oea', $embed, $matches, $attr, $url, $rawattr );
}

function lumen_asmnt_embed_handler( $matches, $attr, $url, $rawattr ) {
  $assessment_id = esc_attr($matches[1]);

  switch_to_blog(1);
  $external_id = get_user_meta( wp_get_current_user()->ID, 'candelalti_external_userid', true );
  restore_current_blog();

	$parameters = array(
      sprintf('src_url=https://assessments.lumenlearning.com/api/assessments/%d.xml', $assessment_id),
      sprintf('assessment_id=%d', $assessment_id),
      'results_end_point=https://assessments.lumenlearning.com/api',
      'confidence_levels=true',
      'enable_start=true',
      'style=lumen_learning',
      'assessment_kind=formative',
      'external_user_id=' . esc_attr($external_id),
      sprintf('iframe_resize_id=lumen_assessment_%d', $assessment_id),
  );

	$params = implode('&', $parameters);

  $iframe = <<<HTML
  <iframe id="lumen_assessment_%d" class="resizable" src="https://assessments.lumenlearning.com/assessments/load?%s"
  frameborder="0" style="border:none;width:100%%;height:100%%;min-height:575px;"></iframe>
HTML;
	$embed = sprintf( $iframe, $assessment_id, $params);

	return apply_filters( 'embed_oea', $embed, $matches, $attr, $url, $rawattr );
}

/**
 * Necessary configuration updates and changes when a new blog/book is created.
 */
function pressbooks_new_blog() {
	// Change to a different theme
	switch_theme( 'candela' );

	// Set copyright to display by default
	$options = get_option( 'pressbooks_theme_options_global' );
	$options['copyright_license'] = 1;
	update_option('pressbooks_theme_options_global', $options);

	// Update new blog urls to https
	$urls = array('home', 'siteurl');
	foreach ( $urls as $option ) {
		$value = get_option( $option );
		update_option($option , str_replace( 'http://', 'https://', $value) );
	}

}

/**
 * Update default book info settings.
 *
 * A default book info post is created when the "Book Info" section is first
 * visited but not prior. Unfortunately the wp_insert_post action is not *ONLY*
 * called on new posts as documented so we check for empty values on those we
 * want defaults set for.
 */
function pressbooks_new_book_info( $post_id ) {
	// There is exactly one 'metadata' post per wordpress site
	if ( get_post_type( $post_id ) == 'metadata') {
		$license = get_post_meta( $post_id, 'pb_book_license', TRUE);
		if ( empty( $license ) ) {
			update_post_meta( $post_id, 'pb_book_license', 'cc-by' );
		}

		$copyright_holder = get_post_meta( $post_id, 'pb_copyright_holder', TRUE );
		if ( empty( $copyright_holder ) ) {
			update_post_meta( $post_id, 'pb_copyright_holder', 'Lumen Learning' );
		}
	}
}

/**
 * Add metadata information for Candela.
 */
function add_meta_boxes() {
	x_add_metadata_group( 'candela-book-information', 'metadata', array(
		'label' => __( 'Candela Book Information', 'pressbooks' ),
		'priority' => 'high',
	) );

	$fields = array(
		'candela-course-id' => array(
			'label' => __('Course Id'),
		),
		'candela-faculty-first-name' => array(
			'label' => __('Faculty First Name'),
		),
		'candela-faculty-last-name' => array(
			'label' => __('Faculty Last Name'),
		),
		'candela-year' => array(
			'label' => __('Year'),
		),
		'candela-semsester' => array(
			'label' => __('Semester'),
			'description' => __(''),
		),
		'candela-previous-textbook-cost' => array(
			'label' => __('Previous Textbook Cost'),
			'description' => __('Previous textbook cost rounded down to the nearest dollar.'),
		),
	);

	foreach ($fields as $key => $info) {
		$info['group'] = 'candela-book-information';
		x_add_metadata_field( $key, 'metadata', $info);
	}

  add_meta_box( 'nav-links', 'Edit Navigation Links', __NAMESPACE__ . '\nav_links', 'chapter', 'side', 'low' );
}

function nav_links() {
  echo(edit_post_link("Previous", '', '', get_pb_page_id("prev")) . " - ");
  echo(edit_post_link("Next", '', '', get_pb_page_id('next')));
}

/**
 * Fetch next or previous Pressbooks post ID
 * This is taken from PB's inner code to find the next page
 *
 * @param string $what prev, next
 *
 * @return ID of requested post
 */
function get_pb_page_id( $what = 'next' ) {

  global $blog_id;
  global $post;

  $current_post_id = $post->ID;
  $book_structure = \PressBooks\Book::getBookStructure();
  $order = $book_structure['__order'];
  $pos = array_keys( $order );

  $what = ( $what == 'next' ? 'next' : 'prev' );

  // Move internal pointer to correct position
  reset( $pos );
  while ( $find_me = current( $pos ) ) {
    if ( $find_me == $current_post_id ) {
      break;
    } else {
      next( $pos );
    }
  }

  // Get next/previous
  $what( $pos );
  while ( $post_id = current( $pos ) ) {
    if ( $order[$post_id]['post_status'] == 'publish' ) {
      break;
    } elseif ( current_user_can_for_blog( $blog_id, 'read' ) ) {
      break;
    } else {
      $what( $pos );
    }
  }

  return $post_id;
}

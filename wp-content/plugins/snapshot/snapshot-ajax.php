<?php
define('DONOTCACHEPAGE', '1');

// We pass the WP ABSPATH (encoded) so that we don't have to guess on the location of the wp-load.php file.
if ((!isset($_POST['snapshot-settings-abspath'])) || (empty($_POST['snapshot-settings-abspath'])))
	die();

if ((!isset($_POST['function'])) || (empty($_POST['function']))) die();

// To let out own plugin know we are in SHORTINIT mode
define('WPMUDEV_CHAT_SHORTINIT', true);

// But when polling for new messages we only want minimal WP no plugins loaded.
define( 'SHORTINIT', true );
define( 'WP_USE_THEMES', false );

// Load in WP core.
require( base64_decode($_POST['snapshot-settings-abspath']) .'wp-load.php' );

// The following taken from wp-load.php. Comment out everything we don't need.
// **************************************************************************
require_once( ABSPATH . WPINC . '/l10n.php' );
//require( ABSPATH . WPINC . '/class-wp-walker.php' );
//require( ABSPATH . WPINC . '/class-wp-ajax-response.php' );
require( ABSPATH . WPINC . '/formatting.php' );
require( ABSPATH . WPINC . '/capabilities.php' );
//require( ABSPATH . WPINC . '/query.php' );
//require( ABSPATH . WPINC . '/theme.php' );
//require( ABSPATH . WPINC . '/class-wp-theme.php' );
//require( ABSPATH . WPINC . '/template.php' );
require( ABSPATH . WPINC . '/user.php' );
require( ABSPATH . WPINC . '/meta.php' );
//require( ABSPATH . WPINC . '/general-template.php' );
require( ABSPATH . WPINC . '/link-template.php' );
//require( ABSPATH . WPINC . '/author-template.php' );
require( ABSPATH . WPINC . '/post.php' );
//require( ABSPATH . WPINC . '/post-template.php' );
//require( ABSPATH . WPINC . '/revision.php' );
//require( ABSPATH . WPINC . '/post-formats.php' );
//require( ABSPATH . WPINC . '/post-thumbnail-template.php' );
//require( ABSPATH . WPINC . '/category.php' );
//require( ABSPATH . WPINC . '/category-template.php' );
//require( ABSPATH . WPINC . '/comment.php' );
//require( ABSPATH . WPINC . '/comment-template.php' );
//require( ABSPATH . WPINC . '/rewrite.php' );
//require( ABSPATH . WPINC . '/feed.php' );
//require( ABSPATH . WPINC . '/bookmark.php' );
//require( ABSPATH . WPINC . '/bookmark-template.php' );
require( ABSPATH . WPINC . '/kses.php' );
//require( ABSPATH . WPINC . '/cron.php' );
//require( ABSPATH . WPINC . '/deprecated.php' );
//require( ABSPATH . WPINC . '/script-loader.php' );
//require( ABSPATH . WPINC . '/taxonomy.php' );
//require( ABSPATH . WPINC . '/update.php' );
//require( ABSPATH . WPINC . '/canonical.php' );
//require( ABSPATH . WPINC . '/shortcodes.php' );
//require( ABSPATH . WPINC . '/class-wp-embed.php' );
//require( ABSPATH . WPINC . '/media.php' );
//require( ABSPATH . WPINC . '/http.php' );
//require( ABSPATH . WPINC . '/class-http.php' );
//require( ABSPATH . WPINC . '/widgets.php' );
//require( ABSPATH . WPINC . '/nav-menu.php' );
//require( ABSPATH . WPINC . '/nav-menu-template.php' );
//require( ABSPATH . WPINC . '/admin-bar.php' );

wp_plugin_directory_constants();
wp_cookie_constants();

require( ABSPATH . WPINC . '/vars.php' );

require( ABSPATH . WPINC . '/pluggable.php' );
require( ABSPATH . WPINC . '/pluggable-deprecated.php' );

// Set internal encoding.
wp_set_internal_encoding();

// Define constants which affect functionality if not already defined.
wp_functionality_constants();

// Add magic quotes and set up $_REQUEST ( $_GET + $_POST )
wp_magic_quotes();

// Setup for WP_PLUGIN_URL and others. See it in wp-includes/default-constants.php
//wp_plugin_directory_constants();
//wp_cookie_constants();
smilies_init();

$wp = new WP();
// Set up current user.

$wp->init();

// Load the default text localization domain.
load_default_textdomain();

$locale = get_locale();
$locale_file = WP_LANG_DIR . "/". $locale .".php";
if ( ( 0 === validate_file( $locale ) ) && is_readable( $locale_file ) )
	require( $locale_file );
unset( $locale_file );

// Pull in locale data after loading text domain.
require_once( ABSPATH . WPINC . '/locale.php' );

/**
 * WordPress Locale object for loading locale domain date and various strings.
 * @global object $wp_locale
 * @since 2.1.0
 */
$GLOBALS['wp_locale'] = new WP_Locale();
// **************************************************************************
// end wp-load.php

// Now load out plugin code. Using as a library here.
include_once( dirname(__FILE__) . '/snapshot.php');
$$wpmudev_snapshot->snapshot_ajax_restore_proc();
die();

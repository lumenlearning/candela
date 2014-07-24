<?php
/*
 * @wordpress-plugin
 * Plugin Name:       Candela Links
 * Description:       Rewrite https links to open in a new window if we are in an iframe.
 * Version:           0.1
 * Author:            Lumen Learning
 * Author URI:        http://lumenlearning.com
 * Text Domain:       lumen
 * License:           MIT
 * GitHub Plugin URI: https://github.com/lumenlearning/candela-lti
 */

namespace Candela\Links;

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
  die();
}

if ( ! defined( 'CANDELA_LINKS_PLUGIN_DIR' ) ) {
  define ( 'CANDELA_LINKS_PLUGIN_DIR', __DIR__ . '/' );
}

if ( ! defined( 'CANDELA_LINKS_PLUGIN_URL' ) ) {
  define ( 'CANDELA_LINKS_PLUGIN_URL', plugin_dir_url( __FILE__ ) . '/');
}


/**
 * Add our javascript to rewrite https links to open in a new window.
 */
function add_javascript() {
  wp_enqueue_script( 'candela-links', CANDELA_LINKS_PLUGIN_URL . 'candela-links.js' , array('jquery'), false, true );
}

add_action( 'init', '\Candela\Links\add_javascript' );

?>

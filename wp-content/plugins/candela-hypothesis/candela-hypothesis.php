<?php
/*
 * Plugin Name:       Candela Hypothesis
 * Plugin URI:        http://hypothes.is/
 * Description:       This is a fork of the hypothesis plugin included in pressbooks-textbooks originally authored by Tim Owens.
 * Author:            Tim Owens, Lumen Learning
 * Author URI:        http://lumenlearning.com
 * Text Domain:       lti
 * License:           GPLv2 or later
 * GitHub Plugin URI: https://github.com/lumenlearning/candela-lti
*/
namespace Candela\Hypothesis;

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
  die();
}

/**
 * Add Hypothesis over https
 */
function add_javascript() {
    wp_enqueue_script( 'hypothesis', 'https://hypothes.is/app/embed.js', '', false, true );
}

add_action( 'init', '\Candela\Hypothesis\add_javascript' );

?>

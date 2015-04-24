<?php
/**
 * @package Candela Analytics
 * @version 0.1
 */
/*
Plugin Name: Candela Analytics
Plugin URI: http://lumenlearning.com/
Description: Adds Google Analyics tracking code to the theme header. This plugin assumes that you will set LUMEN_GA_WEB_PROPERTY_ID and LUMEN_GA_COOKIE_DOMAIN in wp-config.php.
Version: 0.1
Author URI: http://lumenlearning.com
*/

//
add_action('wp_head', 'lumen_ga_script');

define('LUMEN_GA_USERMETA_UUID', 'lumen_ga_uuid');
define('LUMEN_GA_UUID_LENGTH', 32);

function lumen_ga_script() {
  if ( defined( 'LUMEN_GA_WEB_PROPERTY_ID' ) && defined( 'LUMEN_GA_COOKIE_DOMAIN' ) ) {
    lumen_ga_header();
    lumen_ga_custom();
    lumen_ga_footer();
  }
}

function lumen_ga_header() {
  print "<script>
    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
    (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
    m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
    })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
  ";
}

function lumen_ga_footer() {
  print "\n</script>\n";
}

/**
 * Outputs customized google analytics code.
 */
function lumen_ga_custom() {
  print "ga('create', '" . LUMEN_GA_WEB_PROPERTY_ID . "', '" . LUMEN_GA_COOKIE_DOMAIN . "');\n";
  print "ga('send', 'pageview');\n";

  $uuid = lumen_ga_get_current_user_uuid();
  if (!empty($uuid)) {
    print "ga('set', '&uid', '" . $uuid . "');\n";
  }
}

/**
 * Return a uuid for the current user or the empty string if the user is not
 * logged in. This has the side effect of creating a uuid for the current user
 * if one does not exist.
 */
function lumen_ga_get_current_user_uuid() {
  if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    switch_to_blog(1);
    $uuid = get_user_meta( $current_user->ID, LUMEN_GA_USERMETA_UUID, TRUE);
    restore_current_blog();

    if (empty($uuid)) {
      $uuid = lumen_ga_next_uuid();
      switch_to_blog(1);
      update_user_meta( $current_user->ID, LUMEN_GA_USERMETA_UUID, $uuid);
      restore_current_blog();
    }
    return $uuid;
  }
  return '';
}

/**
 * Find a suitable next uuid.
 */
function lumen_ga_next_uuid() {
  $uuid = base64_encode(openssl_random_pseudo_bytes(LUMEN_GA_UUID_LENGTH));
  while (lumen_ga_uuid_exists($uuid)) {
    $uuid = base64_encode(openssl_random_pseudo_bytes(LUMEN_GA_UUID_LENGTH));
  }
  return $uuid;
}

/**
 * Returns true if the given uuid exists for any user.
 */
function lumen_ga_uuid_exists($uuid) {
    switch_to_blog(1);
    $users = get_users(array(
      'meta_key' => LUMEN_GA_USERMETA_UID,
      'meta_value' => $uuid,
    ));
    restore_current_blog();
    if (empty($users)) {
      return FALSE;
    }
    return TRUE;
}

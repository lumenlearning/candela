<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Candela License
 * Description:       Page license for Candela
 * Version:           0.1
 * Author:            Lumen Learning
 * Author URI:        http://lumenlearning.com
 * Text Domain:       lti
 * License:           MIT
 * GitHub Plugin URI: https://github.com/lumenlearning/candela-license
 */

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

// Do our necessary plugin setup and add_action routines.
CandelaLicense::init();

class CandelaLicense {
  /**
   * Takes care of registering our hooks and setting constants.
   */
  public static function init() {
    if ( ! defined( 'CANDELA_LICENSE_FIELD' ) ) {
      define('CANDELA_LICENSE_FIELD', '_candela_license');
    }

    define('CANDELA_LICENSE_DEFAULT', 'cc-by');

    add_action( 'admin_menu', array(__CLASS__, 'admin_menu' ) );
    add_action( 'admin_init', array(__CLASS__, 'settings_api_init') );
    add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
    add_action( 'save_post', array( __CLASS__, 'save'), 10, 2 );

    add_filter( 'pre_update_option_' . CANDELA_LICENSE_FIELD, array( __CLASS__, 'update_license_default' ), 10, 2 );
  }

  /**
   * Return an array of post types to add citations to.
   */
  public static function postTypes() {
    return array(
      'back-matter',
      'chapter',
      'front-matter',
      'part',
    );
  }

  /**
   * Attach custom meta fields.
   *
   * @see http://codex.wordpress.org/Function_Reference/add_meta_box
   */
  public static function add_meta_boxes() {
    $types = CandelaLicense::postTypes();
    foreach ( $types as $type ) {
      add_meta_box('license', 'Page License', array( __CLASS__, 'add_license_meta' ), $type, 'normal' );
    }
  }

  /**
   * Given a post ID render the license field for this field.
   */
  public static function renderLicense( $post_id ) {
    $license = get_post_meta( $post_id, CANDELA_LICENSE_FIELD, true);
    $options = CandelaLicense::GetOptions(array($license));

    if (!empty($options[$license]['link'] && !empty($options[$license]['image'] ) ) ) {
      echo '<a href="' . $options[$license]['link'] . '" rel="license"><img src="' . $options[$license]['image'] . '"></a>';
    }
    else {
      echo '<meta name="DC.rights.license" content="' . $options[$license]['label'] . '" >';
      echo '<div class="license">' . $options[$license]['label'] . '</div>';
    }
  }

  /**
   *
   */
  public static function add_license_meta( $post, $metabox ) {
    // Use get_post_meta to retrieve an existing value from the database.
    $license = get_post_meta( $post->ID, CANDELA_LICENSE_FIELD, true);
    $options = CandelaLicense::GetOptions(array($license));
    $markup = '<select name="' . CANDELA_LICENSE_FIELD . '">';
    foreach ( $options as $value => $option ) {
      $markup .= '<option value="' . esc_attr($value) . '" ' . ($option['selected'] ? 'selected' : '') . '>' . esc_html( $option['label'] ) . '</option>';
    }
    $markup .= '</select>';
    echo $markup;
  }

  public static function GetOptions($selected = array()) {
    $options = array(
      'pd' => array(
        'label' => __( 'Public Domain' ),
        'link' => 'https://creativecommons.org/about/pdm',
        'image' => 'http://i.creativecommons.org/p/mark/1.0/88x31.png',
      ),
      'cc0' => array(
        'label' => __( 'CC0 ' ),
        'link' => 'https://creativecommons.org/publicdomain/zero/1.0/',
        'image' => 'http://i.creativecommons.org/p/zero/1.0/88x31.png',
      ),
      'cc-by' => array(
        'label' => __( 'CC BY' ),
        'link' => 'http://creativecommons.org/licenses/by/4.0/',
        'image' => 'https://i.creativecommons.org/l/by/4.0/88x31.png',
      ),
      'cc-by-sa' => array(
        'label' => __( 'CC BY-SA' ),
        'link' => 'http://creativecommons.org/licenses/by-sa/4.0/',
        'image' => 'https://i.creativecommons.org/l/by-sa/4.0/88x31.png',
      ),
      'cc-by-nd' => array(
        'label' => __( 'CC BY-ND' ),
        'link' => 'http://creativecommons.org/licenses/by-nd/4.0/',
        'image' => 'https://i.creativecommons.org/l/by-nd/4.0/88x31.png',
      ),
      'cc-by-nc' => array(
        'label' => __( 'CC BY-NC' ),
        'link' => 'http://creativecommons.org/licenses/by-nc/4.0/',
        'image' => 'https://i.creativecommons.org/l/by-nc/4.0/88x31.png',
      ),
      'cc-by-nc-sa' => array(
        'label' => __( 'CC BY-NC-SA' ),
        'link' => 'http://creativecommons.org/licenses/by-nc-sa/4.0/',
        'image' => 'https://i.creativecommons.org/l/by-nc-sa/4.0/88x31.png',
      ),
      'cc-by-nc-nd' => array(
        'label' => __( 'CC BY-NC-ND' ),
        'link' => 'http://creativecommons.org/licenses/by-nc-nd/4.0/',
        'image' => 'https://i.creativecommons.org/l/by-nc-nd/4.0/88x31.png',
      ),
      'arr' => array(
        'label' => __( 'All Rights Reserved' ),
        'link' => '',
        'image' => '',
      ),
      'other' => array(
        'label' => __( 'Other' ),
        'link' => '',
        'image' => '',
      ),
    );

    foreach ( $options as $option => $label ) {
      $options[$option]['selected'] = (in_array($option, $selected) ? TRUE : FALSE);
    }
    return $options;
  }

  /**
   * Save a post submitted via form.
   */
  public static function save( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
      return $post_id;
    }

    if ( ! current_user_can( 'edit_page', $post_id) ) {
      return $post_id;
    }

    $citations = array();

    $types = CandelaLicense::postTypes();

    $licenses = array_keys(CandelaLicense::GetOptions());
    if ( in_array( $post->post_type, $types ) ) {
      if( isset( $_REQUEST[CANDELA_LICENSE_FIELD] ) && in_array($_REQUEST[CANDELA_LICENSE_FIELD], $licenses)) {
        $value = $_REQUEST[CANDELA_LICENSE_FIELD];
      }
      elseif ( isset( $post->{CANDELA_LICENSE_FIELD} ) && in_array($post->{CANDELA_LICENSE_FIELD}, $licenses)) {
        $value = $post->{CANDELA_LICENSE_FIELD};
      }
      else {
        $value = get_option(CANDELA_LICENSE_FIELD, CANDELA_LICENSE_DEFAULT);
      }
      update_post_meta( $post_id, CANDELA_LICENSE_FIELD, $value );
    }
  }

  public static function admin_menu() {
    add_options_page(
      __('Candela License', 'candela-license'),
      __('Candela License', 'candela-license'),
      'manage_options',
      'candela-license',
      array(__CLASS__, 'candela_license_options_page')
    );
  }

  public static function settings_api_init() {
    // pressbooks_theme_options_global
    add_settings_section(
      'candela_license_section',
      __('Default License Settings', 'candela-license'),
      array(__CLASS__, 'license_settings_section_callback'),
      'candela-license'
    );

    add_settings_field(
      CANDELA_LICENSE_FIELD,
      __('License', 'candela-license'),
      array(__CLASS__, 'license_setting_callback'),
      'candela-license',
      'candela_license_section'
    );

    register_setting('candela-settings-group', CANDELA_LICENSE_FIELD);
  }

  public static function license_settings_section_callback() {
  }

  public static function license_setting_callback() {
    $license = get_option(CANDELA_LICENSE_FIELD, CANDELA_LICENSE_DEFAULT);
    $options = CandelaLicense::GetOptions(array($license));
    $markup = '<select name="' . CANDELA_LICENSE_FIELD . '">';
    foreach ( $options as $value => $option ) {
      $markup .= '<option value="' . esc_attr($value) . '" ' . ($option['selected'] ? 'selected' : '') . '>' . esc_html( $option['label'] ) . '</option>';
    }
    $markup .= '</select>';
    echo $markup;
  }

  public static function candela_license_options_page() {
    print '<div class="wrap">';
    print '<h2>' . __('License', 'candela-license') . '</h2>';
    print '<form action="options.php" method="POST">';
    settings_fields( 'candela-settings-group' );
    do_settings_sections( 'candela-license' );
    submit_button(__('Set license on all pages', 'candela-license') );

    print "<script type=\"text/javascript\">
      jQuery( document ).ready( function( $ ) {
        $('#submit').click(function() {
          if (!confirm(\"Are you sure you want to update licenses for *every* page in this book?\")) {
            event.preventDefault();
          }
        });
      });
    </script>";
    print '</form>';
    print '</div>';
  }

  public static function update_license_default($new, $old) {
    // iterate over all pages and update license.
    $types = CandelaLicense::postTypes();

    foreach ($types as $type) {
      $posts = get_posts(array('post_type' => $type, 'posts_status' => 'any' ) );
      foreach ($posts as $post) {
        update_post_meta( $post->ID, CANDELA_LICENSE_FIELD, $new );
      }
    }
    return $new;
  }
}


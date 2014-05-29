<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Candela License
 * Description:       Page license for Candela
 * Version:           0.1
 * Author:            Jeff Graham
 * Author URI:        http://funnymonkey.com
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

    add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
    add_action( 'save_post', array( __CLASS__, 'save') );
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
   *
   */
  public static function add_license_meta( $post, $metabox ) {
    // Use get_post_meta to retrieve an existing value from the database.
    $license = get_post_meta( $post->ID, CANDELA_LICENSE_FIELD, true);
    $options = CandelaLicense::GetOptions(array($license));
    $markup = '<select name="candela-license">';
    foreach ( $options as $value => $option ) {
      $markup .= '<option value="' . esc_attr($value) . '" ' . ($option['selected'] ? 'selected' : '') . '>' . esc_html( $option['label'] ) . '</option>';
    }
    $markup .= '</select>';
    echo $markup;
  }

  public static function GetOptions($selected = array()) {
    $options = array(
      'pd' =>  __( 'Public Domain' ),
      'cc0' =>  __( 'CC0 ' ),
      'cc-by' =>  __( 'CC BY' ),
      'cc-by-sa' =>  __( 'CC BY-SA' ),
      'cc-by-nd' =>  __( 'CC BY-ND' ),
      'cc-by-nc' =>  __( 'CC BY-NC' ),
      'cc-by-nc-sa' =>  __( 'CC BY-NC-SA' ),
      'cc-by-nc-nd' =>  __( 'CC BY-NC-ND' ),
      'arr' =>  __( 'All Rights Reserved' ),
      'other' =>  __( 'Other' ),
    );

    $result = array();
    foreach ( $options as $option => $label ) {
      $result[$option] = array(
        'label' => $label,
        'selected' => (in_array($option, $selected) ? TRUE : FALSE),
      );
    }

    return $result;
  }

  /**
   * Save a post submitted via form.
   */
  public static function save( $post_id ) {
    error_log(var_export($_POST,1));
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
      return $post_id;
    }

    if ( ! current_user_can( 'edit_page', $post_id) ) {
      return $post_id;
    }

    $citations = array();

    $types = CandelaLicense::postTypes();

    $licenses = array_keys(CandelaLicense::GetOptions());

    if ( isset( $_POST['post_type'] ) && in_array( $_POST['post_type'], $types ) ) {
      if ( isset( $_POST['candela-license'] ) && in_array($_POST['candela-license'], $licenses)) {
        update_post_meta( $post_id, CANDELA_LICENSE_FIELD, $_POST['candela-license']);
      }
    }
  }
}


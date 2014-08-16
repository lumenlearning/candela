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

    add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
    add_action( 'save_post', array( __CLASS__, 'save') );
    add_filter( 'pb_import_metakeys', array( __CLASS__, 'get_import_metakeys') );
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
      echo '<a href="' . $options[$license]['link'] . ' rel="license"><img src="' . $options[$license]['image'] . '"></a>';
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
    $markup = '<select name="candela-license">';
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
   * Add Candela License to to-import meta 
   */
  public static function get_import_metakeys( $fields ) {
  	$fields[] = CANDELA_LICENSE_FIELD; 
  	return $fields; 
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


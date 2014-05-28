<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Candela Citations
 * Description:       Citations for Candela
 * Version:           0.1
 * Author:            Jeff Graham
 * Author URI:        http://funnymonkey.com
 * Text Domain:       lti
 * License:           MIT
 * GitHub Plugin URI: https://github.com/lumenlearning/candela-citation
 */

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

// Do our necessary plugin setup and add_action routines.
CandelaCitation::init();

class CandelaCitation {
  /**
   * Takes care of registering our hooks and setting constants.
   */
  public static function init() {
    if ( ! defined( 'CANDELA_CITATION_FIELD' ) ) {
      define('CANDELA_CITATION_FIELD', '_candela_citation');
    }

    add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
    add_action( 'save_post', array( __CLASS__, 'save') );
  }

  /**
   * Attach custom meta fields.
   *
   * @see http://codex.wordpress.org/Function_Reference/add_meta_box
   */
  public static function add_meta_boxes() {
    add_meta_box('citations', 'Citations', array( __CLASS__, 'add_citation_meta' ), 'lti_consumer', 'normal' );
  }

  /**
   *
   */
  public static function add_citation_meta( $post, $metabox ) {
    // Use get_post_meta to retrieve an existing value from the database.
    $citations = get_post_meta( $post->ID, CANDELA_CITATION_FIELD, true);
    if ( ! empty( $citations ) ) {
      $citations = unserialize( $citations );
    }
    else {
      $citations = array();
    }


    $rows = array();
    foreach ( $citations as $citation ) {
      $rows[] = CandelaCitation::get_meta_row( $citation );
    }

    $rows[] = CandelaCitation::get_meta_row();
    $first = TRUE;
    echo '<table>';
    $i = 0;
    foreach ($rows as $fields) {
      $headers = array();
      $row = array();
      foreach ($fields as $field) {
        if ( $first ) {
          $headers[] = $field['label'];
        }
        $row[] = $field['widget'];
      }

      if ( $first ) {
        echo '<thead><tr><th>';
        echo implode( '</th><th>', $headers );
        echo '</th></tr></thead><tbody>';
        $first = FALSE;
      }

      echo '<tr><td>';
      echo implode( '</td><td>', str_replace('@@INDEX@@', $i, $row) );
      echo '</td></tr>';
      $i++;
    }

    // @todo jQuery append add more widgets.

    echo '</tbody></table>';
  }

  public static function citation_fields() {
    return array(
      'name' => array(
        'type' => 'text',
        'label' => __( 'Name' ),
      ),
      'url' => array(
        'type' => 'text',
        'label' => __( 'URL' ),
      ),
      'published' => array(
        'type' => 'date',
        'label' => __( 'Date Published' ),
      ),
      'type' => array(
        'type' => 'select',
        'label' => __( 'Learning Resource Type' ),
      ),
      'license' => array(
        'type' => 'select',
        'label' => __( 'License' ),
      ),
    );
  }

  public static function get_meta_row( $citation = array() ) {

    $fields = CandelaCitation::citation_fields();
    if ( empty($citation) ) {
      $citation = array_fill_keys( array_keys( $fields ), '' );
    }

    foreach ( $fields as $key => $widget ) {
      switch ( $widget['type'] ) {
        case 'select':
          if ( is_array($citation[$key]) ) {
            $fields[$key]['options'] = CandelaCitation::GetOptions($key, $citation[$key]);
          }
          else {
            $fields[$key]['options'] = CandelaCitation::GetOptions($key, array($citation[$key]));
          }
          break;
        default:
          $fields[$key]['value'] = $citation[$key];
          break;
      }
    }

    $row = array();
    foreach ($fields as $key => $widget) {
      switch ($widget['type']) {
        case 'select':
          $markup = '<select name="citation-' . esc_attr($key) . '[@@INDEX@@]">';
          foreach ( $widget['options'] as $value => $option ) {
            $markup .= '<option value="' . esc_attr($value) . '" ' . ($option['selected'] ? 'selected' : '') . '>' . esc_html( $option['label'] ) . '</option>';
          }
          $markup .= '</select>';
          $row[$key] = array(
            'widget' => $markup,
            'label' => $widget['label'],
          );
          break;
        default:
          $row[$key] = array(
            'widget' => '<input name="citation-' . esc_attr($key) . '[@@INDEX@@]" type="' . $widget['type'] . '" value="' . esc_attr( $widget['value'] ) . '">',
            'label' => $widget['label'],
          );
          break;
      }
    }

    return $row;
  }

  public static function GetOptions($field, $selected) {
    switch ($field) {
      case 'type':
        $options = array(
          'handout' => __('Handout'),
        );
        break;
      case 'license':
        $options = array(
          'cc-by' =>  __( 'CC Attribution (CC BY)' ),
          'cc-by-nd' =>  __( 'CC Attribution-NoDerivs (CC BY-ND)' ),
          'cc-by-sa' =>  __( 'CC Attribution-ShareAlike (CC BY-SA)' ),
          'cc-by-nc' =>  __( 'CC Attribution-NonCommercial (CC BY-NC)' ),
          'cc-by-nc-sa' =>  __( 'CC Attribution-NonCommerial-ShareAlike (CC BY-NC-SA)' ),
          'cc-by-nc-nd' =>  __( 'CC Attribution-NonCommercial-NoDerivs (CC BY-NC-ND)' ),
          'cc0' =>  __( 'CC0 No Rights Reserved (CC0)' ),
          'pd' =>  __( 'Public Domain' ),
          'copyright' =>  __( 'All Rights Reserved' ),
          'other' =>  __( 'Other' ),
        );
        break;
    }

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

    $fields = CandelaCitation::citation_fields();

    // Use the first citation field to determine if citation fields were submitted.
    $key = key($fields);

    $post_field = 'citation-' . $key;
    if ( isset($_POST[$post_field] ) ) {
      // We have field data for citations, iterate over
      foreach ( $_POST[$post_field] as $index => $junk) {
        foreach ($fields as $field => $info) {
          // Re-associate fields per citation
          $citations[$index][$field] = $_POST['citation-' . $field][$index];
        }

        // Name is required
        if (empty($citations[$index]['name'])) {
          unset($citations[$index]);
        }
      }


    }

    if ( ! empty($citations) ) {
      update_post_meta( $post_id, CANDELA_CITATION_FIELD, serialize( $citations ) );
    }
  }

}


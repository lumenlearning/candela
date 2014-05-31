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
    $types = CandelaCitation::postTypes();
    foreach ( $types as $type ) {
      add_meta_box('citations', 'Citations', array( __CLASS__, 'add_citation_meta' ), $type, 'normal' );
    }
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

  /**
   *
   */
  public static function renderCitation( $post_id ) {
    // Use get_post_meta to retrieve an existing value from the database.
    $citations = get_post_meta( $post_id, CANDELA_CITATION_FIELD, true);
    if ( ! empty( $citations ) ) {
      $citations = unserialize( $citations );
    }
    else {
      $citations = array();
    }

    $grouped = array();

    $license = CandelaCitation::getOptions('license');
    foreach ($citations as $citation) {
      switch ( $citation['type'] ) {
        case 'original';
          $cite = 'Original content contributed by [AUTHOR] of [ORGANIZATION] to [PROJECT].';
          break;
        case 'cc';
          $cite = 'Content created by [AUTHOR] of [ORGANIZATION] for [PROJECT], originally published at [URL] under a [LICENSE] license.';
          break;
        case 'copyrighted_video';
          $cite = 'The video of [DESCRIPTION] was created by [AUTHOR] of [ORGANIZATION] for [PROJECT] and published at [URL]. This video is copyrighted and is not licensed under an open license. Embedded as permitted by [LICENSE_TERMS].';
          break;
        case 'pd';
          $cite = 'Content created (or published) by [AUTHOR] or [ORGANIZATION] (at [URL]).';
          break;
        case 'cc-attribution';
          $cite = '[LICENSE_TERMS]';
          break;
        case 'lumen';
          $cite = 'Content created by [AUTHOR] of [ORGANIZATION] for [PROJECT], originally published at [URL] under a [LICENSE] license.';
          break;
      }

      // Replace templated portions if provided in citation.
      $cite = empty( $citation['description'] ) ? $cite : str_replace('[DESCRIPTION]', $citation['description'], $cite);
      $cite = empty( $citation['author'] ) ? $cite : str_replace('[AUTHOR]', $citation['author'], $cite);
      $cite = empty( $citation['organization'] ) ? $cite : str_replace('[ORGANIZATION]', $citation['organization'], $cite);
      $cite = empty( $citation['url'] ) ? $cite : str_replace('[URL]', $citation['url'], $cite);
      $cite = empty( $citation['project'] ) ? $cite : str_replace('[PROJECT]', $citation['project'], $cite);
      $cite = empty( $citation['license'] ) ? $cite : str_replace('[LICENSE]', $license[$citation['license']], $cite);
      $cite = empty( $citation['license_terms'] ) ? $cite : str_replace('[LICENSE_TERMS]', $citation['license_terms'], $cite);
      $grouped[$citation['type']][] = $cite;

    }

    $output = array();
    if ( ! empty($grouped) ) {
      $types = CandelaCitation::getOptions('type');

      foreach ( $types as $type => $info ) {
        if ( ! empty( $grouped[$type] ) ) {
          array_merge( $output, $grouped[$type] );
        }
      }

      if (! empty( $grouped['original'] ) ) {
        foreach ( $groups['original'] as $citation ) {
          $output[] = $citation;
        }
      }
    }
    return $output;
  }

  public static function citation_fields() {
    return array(
      'type' => array(
        'type' => 'select',
        'label' => __( 'Type' ),
      ),
      'description' => array(
        'type' => 'text',
        'label' => __( 'Description' ),
      ),
      'author' => array(
        'type' => 'text',
        'label' => __( 'Author' ),
      ),
      'organization' => array(
        'type' => 'text',
        'label' => __( 'Organization' ),
      ),
      'url' => array(
        'type' => 'text',
        'label' => __( 'URL' ),
      ),
      'project' => array(
        'type' => 'text',
        'label' => __( 'Project' ),
      ),
      'license' => array(
        'type' => 'select',
        'label' => __( 'Licensing' ),
      ),
      'license_terms' => array(
        'type' => 'text',
        'label' => __( 'License terms' ),
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
          $fields[$key]['value'] = empty( $citation[$key] ) ? '' : $citation[$key];
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

  public static function GetOptions($field, $selected = array()) {
    switch ($field) {
      case 'type':
        // Note that the order here determines order on output. See renderCitation
        $options = array(
          '' => __('Choose citation type'),
          'original' => __('Original content'),
          'cc' => __('CC licensed content'),
          'copyrighted_video' => __('Copyrighted video content'),
          'pd' => __('Public domain content'),
          'cc-attribution' => __('CC with specific attribution'),
          'lumen' => __('Lumen Learning authored content'),
        );
        break;
      case 'license':
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

    $types = CandelaCitation::postTypes();
    $fields = CandelaCitation::citation_fields();

    if ( isset( $_POST['post_type'] ) && in_array( $_POST['post_type'], $types ) ) {
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
          // Citation type is required
          if (empty($citations[$index]['type'])) {
            unset($citations[$index]);
          }
        }
      }
    }

    update_post_meta( $post_id, CANDELA_CITATION_FIELD, serialize( $citations ) );
  }

}


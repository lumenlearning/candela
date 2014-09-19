<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Candela Citations
 * Description:       Citations for Candela
 * Version:           0.1
 * Author:            Lumen Learning
 * Author URI:        http://lumenlearning.com
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

    define('CANDELA_CITATION_DB_VERSION', '1.0');
    define('CANDELA_CITATION_SEPARATOR', '. ');
    define('CANDELA_CITATION_DB_OPTION', 'candela_citation_db_version');

    CandelaCitation::update_db();
    add_action( 'admin_menu', array(__CLASS__, 'admin_menu' ) );
    add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
    add_action( 'save_post', array( __CLASS__, 'save') );

  }

  /**
   *
   */
  public static function update_db() {
    $version = get_option(CANDELA_CITATION_DB_OPTION, '');

    if (empty($version)) {
      update_option(CANDELA_CITATION_DB_OPTION, CANDELA_CITATION_DB_VERSION);
      CandelaCitation::update_to_json_encode();
    }
  }

  /**
   * Previously citaitons were stored as serialized values.
   */
  public static function update_to_json_encode() {
    // Get all post citation data and then update from serialize to json_encode
    // This avoids several issues with serialize when certain meta characters
    // are in the value.
    $types = CandelaCitation::postTypes();

    foreach ($types as $type) {
      $posts = get_posts(array('post_type' => $type, 'post_status' => 'any' ) );
      foreach ($posts as $post) {
        $citations = get_post_meta( $post->ID, CANDELA_CITATION_FIELD, true);
        $citations = unserialize( $citations );
        update_post_meta( $post->ID, CANDELA_CITATION_FIELD, json_encode( $citations ) );
      }
    }
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
      $citations = json_decode( stripslashes( $citations ), TRUE );
    }
    else {
      $citations = array();
    }


    $rows = array();
    foreach ( $citations as $citation ) {
      $rows[] = CandelaCitation::get_meta_row( $citation );
    }

    $rows[] = CandelaCitation::get_meta_row();
    CandelaCitation::citations_table( $rows );
  }

  /**
   *
   */
  public static function renderCitation( $post_id ) {
    // Use get_post_meta to retrieve an existing value from the database.
    $citations = get_post_meta( $post_id, CANDELA_CITATION_FIELD, true);
    if ( ! empty( $citations ) ) {
      $citations = json_decode( stripslashes( $citations ) , TRUE );
    }
    else {
      $citations = array();
    }

    $grouped = array();
    $fields = CandelaCitation::citation_fields();
    $license = CandelaCitation::getOptions('license');

    foreach ($citations as $citation) {
      $parts = array();

      foreach ($fields as $field => $info) {

        if (!empty($citation[$field]) && $field != 'type') {
          $parts[] = $info['prefix'] . esc_html($citation[$field]) . $info['suffix'];
        }
      }
      $grouped[$citation['type']][] = implode(CANDELA_CITATION_SEPARATOR, $parts);
    }

    $output = '';
    if ( ! empty($grouped) ) {
      $types = CandelaCitation::getOptions('type');
      foreach ( $types as $type => $info ) {
        if ( ! empty( $grouped[$type] ) ) {
          $output .= '<h4>' . $info['label'] . '</h4>';
          $output .= '<ul class="citation-list"><li>';
          $output .= implode('</li><li>', $grouped[$type] );
          $output .= '</li></ul>';
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
        'prefix' => '',
        'suffix' => '',
      ),
      'description' => array(
        'type' => 'text',
        'label' => __( 'Description' ),
        'prefix' => '',
        'suffix' => '',
      ),
      'author' => array(
        'type' => 'text',
        'label' => __( 'Author' ),
        'prefix' => '<strong>' . __( 'Authored by' ) . '</strong>: ',
        'suffix' => '',
      ),
      'organization' => array(
        'type' => 'text',
        'label' => __( 'Organization' ),
        'prefix' => '<strong>' . __( 'Provided by' ) . '</strong>: ',
        'suffix' => '',
      ),
      'url' => array(
        'type' => 'text',
        'label' => __( 'URL' ),
        'prefix' => '<strong>' . __( 'Located at' ) . '</strong>: (',
        'suffix' => ')',
      ),
      'project' => array(
        'type' => 'text',
        'label' => __( 'Project' ),
        'prefix' => '<strong>' . __( 'Project' ) . '</strong>: ',
        'suffix' => '',
      ),
      'license' => array(
        'type' => 'select',
        'label' => __( 'Licensing' ),
        'prefix' => '<strong>' . __('License') . '</strong>: <em>',
        'suffix' => '</em>',
      ),
      'license_terms' => array(
        'type' => 'text',
        'label' => __( 'License terms' ),
        'prefix' => '<strong>' . __('License Terms') . '</strong>: ',
        'suffix' => '',
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
          $markup = '<select name="citation-' . esc_attr($key) . '[%%INDEX%%]">';
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
            'widget' => '<input name="citation-' . esc_attr($key) . '[%%INDEX%%]" type="' . $widget['type'] . '" value="' . esc_attr( $widget['value'] ) . '">',
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

    $types = CandelaCitation::postTypes();

    if ( isset( $_POST['post_type'] ) && in_array( $_POST['post_type'], $types ) ) {
      $citations = CandelaCitation::process_citations();
      update_post_meta( $post_id, CANDELA_CITATION_FIELD, json_encode( $citations ) );
    }

  }

  public static function process_citations() {
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
        // Citation type is required
        if (empty($citations[$index]['type'])) {
          unset($citations[$index]);
        }
      }
    }
    return $citations;
  }

  public static function admin_menu() {
    add_options_page(
      __('Candela Citations', 'candela-citation'),
      __('Candela Citations', 'candela-citation'),
      'manage_options',
      'candela-citation',
      array(__CLASS__, 'global_citation_page')
    );
  }

  public static function global_citation_page() {

    // Process incoming form and preload previous citations.
    $rows = array();
    if (!empty($_POST['__citation'])) {
      $citations = CandelaCitation::process_global_form();

      if (!empty($citations)) {
        foreach($citations as $citation) {
          $rows[] = CandelaCitation::get_meta_row( $citation );
        }
      }
    }

    print '<div class="wrap">';
    print '<h2>' . __('Global Citations', 'candela-citation') . '</h2>';
    print '<form method="POST" action="' . get_permalink() . '">';
    print '<input type="hidden" name="__citation" value="1" >';

    $rows[] = CandelaCitation::get_meta_row();
    CandelaCitation::citations_table( $rows );

    print '<input type="submit" id="citation-add-all" name="citation-add-all" value="' .  __('Add citations to every page', 'candela-citation') . '">';
    print '<input type="submit" id="citation-replace-all" name="citation-replace-all" value="' . __('OVERWRITE citations on every page', 'candela-citation') . '">';

    print "<script type=\"text/javascript\">
      jQuery( document ).ready( function( $ ) {
        $('#citation-add-all').click(function() {
          if (!confirm(\"Are you sure you want to add citations to *every* page in this book?\")) {
            event.preventDefault();
          }
        });
      });
    </script>";

    print "<script type=\"text/javascript\">
      jQuery( document ).ready( function( $ ) {
        $('#citation-replace-all').click(function() {
          if (!confirm(\"Are you sure you want to replace all citations in *every* page in this book?\")) {
            event.preventDefault();
          }
        });
      });
    </script>";

    print '</form>';
    print '</div>';

    // Show citations for every book
    $structure = pb_get_book_structure();
    if ( ! empty( $structure['__order'] ) ) {
      $grouped = array();
      $headers = array(__('Post'));
      foreach ( $structure['__order'] as $id => $info ) {
        $post = get_post ( $id );

        // Use get_post_meta to retrieve an existing value from the database.
        $citations = get_post_meta( $id, CANDELA_CITATION_FIELD, true);
        if ( ! empty( $citations ) ) {
          $citations = json_decode( stripslashes( $citations ) , TRUE );
        }
        else {
          $citations = array();
        }
        $fields = CandelaCitation::citation_fields();

        foreach ($citations as $citation) {
          $parts = array();
          $parts[] = '<a href="' . get_permalink( $post->ID ) . '">' . $post->post_title . '</a>';
          foreach ($fields as $field => $info) {
            if ( empty( $headers[$field] ) ) {
              $headers[$field] = $info['label'];
            }
            if (!empty($citation[$field])) {
              $parts[] = esc_html($citation[$field]);
            }
          }
          $grouped[$id][$citation['type']][] = $parts;
        }
      }

      if (!empty( $grouped ) ) {
        print '<div class="wrap"><table>';
        print '<thead><tr>';
        foreach ($headers as $title) {
          print '<th>' . $title . '</th>';
        }
        print '</tr></thead>';

        print '<tbody>';
        foreach ( $grouped as $id => $citations) {
          foreach ( $citations as $type => $parts ) {

            foreach ($parts as $row ) {
              print '<tr>';
              foreach ( $row as $field ) {
                print '<td>' . $field . '</td>';
              }
              print '</tr>';
            }
          }
        }
        print '</tbody>';

        print '</table></div>';
      }
      else {
        print '';
      }
    }
  }

  public static function process_global_form() {
    $citations = CandelaCitation::process_citations();
    if ( ! empty($citations)) {
      if (isset($_POST['citation-replace-all'])) {
        CandelaCitation::replace_all_citations($citations);
      }

      if (isset($_POST['citation-add-all'])) {
        CandelaCitation::add_all_citations($citations);
      }
    }
    return $citations;
  }

  public static function replace_all_citations($citations) {
    $types = CandelaCitation::postTypes();

    foreach ($types as $type) {
      $posts = get_posts(array('post_type' => $type, 'post_status' => 'any' ) );
      foreach ($posts as $post) {
        update_post_meta( $post->ID, CANDELA_CITATION_FIELD, json_encode( $citations ) );
      }
    }
  }

  public static function add_all_citations($citations) {
    $types = CandelaCitation::postTypes();

    foreach ($types as $type) {
      $posts = get_posts(array('post_type' => $type, 'post_status' => 'any' ) );
      foreach ($posts as $post) {
        // Get existing citations and append new ones.
        $existing = get_post_meta( $post->ID, CANDELA_CITATION_FIELD, true);
        if ( ! empty( $existing ) ) {
          $existing = json_decode( stripslashes ( $existing ), TRUE);
          $new = array_merge($existing, $citations);
        }
        else {
          $new = $citations;
        }
        update_post_meta( $post->ID, CANDELA_CITATION_FIELD, json_encode( $new ) );
      }
    }
  }

  /**
   * Add our citation processing vars so that wordpress "understands" them.
   */
  public static function query_vars( $query_vars ) {
    $query_vars[] = '__citation';
    return $query_vars;
  }

  public static function citations_table( $rows ) {
    $first = TRUE;
    $fields = CandelaCitation::citation_fields();
    echo '<table id="citation-table">';
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
      echo implode( '</td><td>', str_replace('%%INDEX%%', $i, $row) );
      echo '</td></tr>';
      $i++;
    }
    echo '</tbody></table>';

    echo '<button id="citation-add-more-button" type="button">';
    _e('Add more citations');
    echo '</button>';
    echo '<script type="text/javascript">
      jQuery( document ).ready( function( $ ) {
        var citationIndex = '. $i . ';
        citationWidgets = \'<tr><td>' . implode( '</td><td>', $row ) . '</td></tr>\';
        $( "#citation-add-more-button" ).click(function() {
          newWidgets = citationWidgets.split("%%INDEX%%").join(citationIndex);
          $( "#citation-table tbody").append(newWidgets);
          citationIndex++;
        });
      });
    </script>';
  }
}


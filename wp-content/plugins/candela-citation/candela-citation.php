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

    add_action( 'admin_menu', array(__CLASS__, 'admin_menu' ) );
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
    CandelaCitation::citations_table( $rows );
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
      $authorblock = '';
      if ( !empty( $citation['author'] ) ) {
	$authorblock .= $citation['author'];
	if ( !empty( $citation['organization'] ) ) {
	  $authorblock .= ' of ' . $citation['organization'];
	}
      } else if ( !empty( $citation['organization'] ) ) {
	$authorblock .= $citation['organization'];
      } 
      if ( !empty( $citation['project'] ) ) {
	if ( $authorblock == '' ) {
	  $authorblock .= $citation['project'];
	} else {
	  $authorblock .= ' [PROJ-TO] ' . $citation['project'];
	}
      }
      if ( $authorblock == '' ) {
	$authorblock = 'UNKNOWN';
      }
      switch ( $citation['type'] ) {
        case 'original';
          $authorblock = str_replace('[PROJ-TO]', 'to', $authorblock);
	  $cite = 'Original content [DESCRIPTION] contributed by [AUTHORBLOCK]';
	  break;
        case 'cc';
          $authorblock = str_replace('[PROJ-TO]', 'for', $authorblock);
          $cite = 'Content [DESCRIPTION] created by [AUTHORBLOCK]';
	  if ( !empty( $citation['url'] ) || !empty( $citation['license'] ) || !empty( $citation['license_terms'] ) ) {
	    $cite .= ', originally published';
	    if ( !empty( $citation['url'] ) ) {
	      $cite .= ' at [URL]';
	    }
	    if ( !empty( $citation['license'] ) ) {
	      $cite .= ' under a [LICENSE] license';
	    }
	    if ( !empty( $citation['license_terms'] ) ) {
	      $cite .= ' with terms [LICENSE_TERMS]';
	    }
	  }
          break;
        case 'copyrighted_video';
          $authorblock = str_replace('[PROJ-TO]', 'for', $authorblock);
          $cite = 'Video [DESCRIPTION] created by [AUTHORBLOCK] ';
	  if ( !empty( $citation['url'] ) ) {
	    $cite .= 'and published at [URL] ';
	  }
	  $cite .= 'This video is copyrighted and is not licensed under an open license. Embedded as permitted by ';
	  if ( !empty( $citation['license_terms'] ) ) {
	    $cite .= $citation['license_terms'];
	  } else {
	    $cite .= ' the Terms of Use';
	  }
	  break;
        case 'pd';
          $authorblock = str_replace('[PROJ-TO]', 'for', $authorblock);
          $cite = 'Content [DESCRIPTION] created (or published) by [AUTHORBLOCK] ';
          if ( !empty( $citation['url'] ) ) {
            $cite .= '(at [URL])';
          }
          break;
        case 'cc-attribution';
          $cite = '[LICENSE_TERMS]';
          break;
        case 'lumen';
          $authorblock = str_replace('[PROJ-TO]', 'for', $authorblock);
          if ($authorblock == 'UNKNOWN') {
          	  $authorblock = 'Lumen Learning';
          }
          $cite = 'Content [DESCRIPTION] created by [AUTHORBLOCK]';
          if ( !empty( $citation['url'] ) || !empty( $citation['license'] ) ) {
	    $cite .= ', originally published ';
	    if ( !empty( $citation['url'] ) ) {
	      $cite .= 'at [URL] ';
	    }
	    if ( !empty( $citation['license'] ) ) {
	      $cite .= 'under a [LICENSE] license';
	    }
	  }
          break;
      }

      // Replace templated portions if provided in citation.
      $cite = str_replace('[AUTHORBLOCK]', $authorblock, $cite);
      $cite = empty( $citation['description'] ) ? str_replace('[DESCRIPTION]', '', $cite) : str_replace('[DESCRIPTION]', '('.$citation['description'].')', $cite);
      //$cite = empty( $citation['author'] ) ? $cite : str_replace('[AUTHOR]', $citation['author'], $cite);
      //$cite = empty( $citation['organization'] ) ? $cite : str_replace('[ORGANIZATION]', $citation['organization'], $cite);
      $cite = empty( $citation['url'] ) ? $cite : str_replace('[URL]', $citation['url'], $cite);
      //$cite = empty( $citation['project'] ) ? $cite : str_replace('[PROJECT]', $citation['project'], $cite);
      $cite = empty( $citation['license'] ) ? $cite : str_replace('[LICENSE]', $license[$citation['license']]['label'], $cite);
      $cite = empty( $citation['license_terms'] ) ? $cite : str_replace('[LICENSE_TERMS]', $citation['license_terms'], $cite);
      $cite .= '.';
      $grouped[$citation['type']][] = $cite;

    }

    $output = array();
    if ( ! empty($grouped) ) {
      $types = CandelaCitation::getOptions('type');

      foreach ( $types as $type => $info ) {
        if ( ! empty( $grouped[$type] ) ) {
          $output = array_merge( $output, $grouped[$type] );
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
      update_post_meta( $post_id, CANDELA_CITATION_FIELD, serialize( $citations ) );
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

    print '</form>';
    print '</div>';
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
      $posts = get_posts(array('post_type' => $type ) );
      foreach ($posts as $post) {
        update_post_meta( $post->ID, CANDELA_CITATION_FIELD, serialize( $citations ) );
      }
    }
  }

  public static function add_all_citations($citations) {
    $types = CandelaCitation::postTypes();

    foreach ($types as $type) {
      $posts = get_posts(array('post_type' => $type ) );
      foreach ($posts as $post) {
        // Get existing citations and append new ones.
        $existing = get_post_meta( $post->ID, CANDELA_CITATION_FIELD, true);
        if ( ! empty( $existing ) ) {
          $existing = unserialize( $existing );
          $new = array_merge($existing, $citations);
        }
        else {
          $new = $citations;
        }
        update_post_meta( $post->ID, CANDELA_CITATION_FIELD, serialize( $new ) );
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


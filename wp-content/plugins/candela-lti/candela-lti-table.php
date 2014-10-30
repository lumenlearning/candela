<?php
// Class to display LTI maps.

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Candela_LTI_Table extends WP_List_Table {
  function __construct() {
    global $status, $page;

    parent::__construct(array(
      'singular' => 'LTI map',
      'plural' => 'LTI maps',
    ));
  }

  function column_default($item, $column_name) {
    return $item[$column_name];
  }

  function column_cb($item) {
    return sprintf('<input type="checkbox" name=ID[]" value="%s" />', $item['ID']);
  }

  function column_target_action($item) {
    return sprintf('<a href="%s">%s</a>', esc_attr($item['target_action']), $item['target_action']);
  }

  function column_user($item) {
    return sprintf('<a href="%s/wp-admin/user-edit.php?user_id=%d">%s</a>', get_site_url(), esc_attr($item['user_id']), $item['user_nicename']);
  }

  function column_lti_info($item) {
    $output = '';
    if (!empty($item['lti_info'])) {
      $values = unserialize($item['lti_info']);
      if (! empty($values) ) {

        $output .= '<a href="#" id="lti-info-toggle-'. $item['ID'] .'">Info</a>';
        $output .= '<div id="lti-info-div-' . $item['ID'] . '" style="display:none;"><dl>';
        ksort($values);
        foreach ($values as $key => $value) {
          $output .= '<dt>' . esc_html($key) . '</dt>';
          $output .= '<dd>' . esc_html($value) . '</dd>';
        }
        $output .= '</dl></div>';
        $output .= '<script type="text/javascript">
            jQuery(function($){
              $(document).ready(function() {
                $("#lti-info-toggle-' . $item['ID'] . '").click(function(){
                  $("#lti-info-div-' . $item['ID'] . '").slideToggle();
                });
              });
            });
          </script>';
      }
    }
    else {
      $output = '';
    }
    return $output;
  }

  function get_columns() {
    return array(
      'cb' => '<input type="checkbox" />',
      'resource_link_id' => __('Link ID', 'candela_lti'),
      'target_action' => __('Action', 'candela_lti'),
      'user' => __('User', 'candela_lti'),
    );
  }

  function get_sortable_columns() {
    return array(
      'resource_link_id' => array('resource_link_id', TRUE),
      'target_action' => array('target_action', FALSE),
      'user' => array('user_nicename', FALSE),
    );
  }

  function get_bulk_actions() {
    return array(
      'delete' => 'Delete',
    );
  }

  function process_bulk_action() {
    global $wpdb;
    $table_name = CANDELA_LTI_TABLE;

    if ('delete' === $this->current_action() ) {
      $ids = isset($_REQUEST['ID']) ? $_REQUEST['ID'] : array();
      if ( is_array($ids) ) {
        $ids = implode(',', $ids);
      }

      if ( ! empty($ids) ) {
        $wpdb->query("DELETE FROM $table_name WHERE id IN( $ids )");
      }
    }

  }

  function prepare_items() {
    global $wpdb;
    $table_name = CANDELA_LTI_TABLE;

    $per_page = 20;

    $columns = $this->get_columns();

    $hidden = array();
    $sortable = $this->get_sortable_columns();

    $this->_column_headers = array($columns, $hidden, $sortable);

    $this->process_bulk_action();

    $total_items = $wpdb->get_var("SELECT count(ID) FROM $table_name");

    $paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged']) - 1) : 0;
    $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'resource_link_id';
    $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';


    $sql = "SELECT l.*, u.user_nicename FROM $table_name l
            LEFT JOIN wp_users u ON u.ID = l.user_id
            ORDER BY $orderby $order
            LIMIT %d OFFSET %d";
    $prepared = $wpdb->prepare($sql, $per_page, $paged);
    $this->items = $wpdb->get_results($prepared, ARRAY_A);

    $this->set_pagination_args(array(
      'total_items' => $total_items, // total items defined above
      'per_page' => $per_page, // per page constant defined at top of method
      'total_pages' => ceil($total_items / $per_page) // calculate pages count
    ));
  }
}

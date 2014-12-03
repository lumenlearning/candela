<?php
namespace Candela\Outcomes;
// Class to display learning outcome collections.

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('WP_List_Table')) {
  require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class CollectionOverviewTable extends \WP_List_Table {
  private $uuid = '';

  function __construct( $uuid ) {
    global $status, $page;
    $this->uuid = $uuid;
    parent::__construct(array(
      'singular' => 'Collection',
      'plural' => 'Collections',
    ));
  }

  function column_default($item, $column_name) {
    return $item[$column_name];
  }

  function column_title($item) {
    return $this->handle_title( $item['uuid'], $item['title'] );
  }

  function column_successor($item) {
    if ( ! empty ( $item['successor'] ) ) {
      return $this->handle_title( $item['successor'], $item['successor_title'] );
    }
    return '';
  }

  function handle_title( $uuid, $title ) {
    $outcome = new Outcome();
    $outcome->uuid = $uuid;
    return sprintf('<a href="%s">%s</a><a href="%s" class="button button-small">%s</a>', esc_attr( $outcome->uri() ), esc_html( $title ), esc_attr( $outcome->uri( TRUE ) ), __('Edit') );
  }


  function get_columns() {
    return array(
      'title' => __('Title'),
      'status' => __('Status'),
      'successor' => __('Successor'),
    );
  }

  function get_sortable_columns() {
    return array(
      'title' => array('title', TRUE),
      'status' => array('status', FALSE),
    );
  }

  function prepare_items() {
    global $wpdb;
    $collections_table = $wpdb->prefix . 'outcomes_collection';
    $outcomes_table = $wpdb->prefix . 'outcomes_outcome';

    $per_page = 20;
    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);

    $total_items = $wpdb->get_var("SELECT count(ID) FROM $collections_table");

    $paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged']) - 1) : 0;
    $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'title';
    $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';

    $sql = "SELECT o.uuid, o.title, o.status, o.successor, s.title as successor_title
        FROM $outcomes_table o
        LEFT JOIN $outcomes_table s ON o.successor = s.uuid
        WHERE o.belongs_to = %s
        ORDER BY $orderby $order
        LIMIT %d OFFSET %d";
    $prepared = $wpdb->prepare($sql, $this->uuid, $per_page, $paged);

    $this->items = $wpdb->get_results($prepared, ARRAY_A);

    $this->set_pagination_args(array(
      'total_items' => $total_items, // total items defined above
      'per_page' => $per_page, // per page constant defined at top of method
      'total_pages' => ceil($total_items / $per_page) // calculate pages count
    ));
  }
}

<?php
namespace Candela\Outcomes;

include_once( __DIR__ . '/class.base.php' );

/**
 * Outcome class
 */
class Outcome extends Base {
  public static $type = 'outcome';
  public $status = 'draft';
  public $successor = '';
  public $belongs_to = '';

  public function load( $uuid ) {
    $item = load_item_by_uuid( $uuid, 'outcome' );

    if ( ! empty( $item ) ) {
      $item->is_new = FALSE;
      foreach ($item as $prop => $value ) {
        $this->$prop = $value;
      }

      $this->uuid = $uuid;

      if ( ! empty( $item->belongs_to ) ) {
        $this->collection = new Collection;
        $this->collection->loadByURI( $item->belongs_to );
      }

      if ( ! empty( $item->successor ) ) {
        $this->successor_outcome = new Outcome;
        $this->successor_outcome->loadByURI( $item->successor );
      }
    }
    else {
      $this->errors['loader']['notfound'] = __('Unable to find item with UUID.', 'candela_outcomes' );
    }
  }

  public function loadByURI( $uri ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'outcomes_outcome';
    $uuid = $wpdb->get_var(
      $wpdb->prepare("
        SELECT uuid FROM $table_name
        WHERE uri = %s",
        $uri
      )
    );
    $this->load( $uuid );
  }

  public function save() {
    global $wpdb;
    $table = $wpdb->prefix . 'outcomes_outcome';

    if ( empty( $this->uri ) ) {
      $this->uri = $this->uri();
    }

    $data = array(
      'uuid' => $this->uuid,
      'user_id' => $this->user_id,
      'uri' => $this->uri,
      'title' => $this->title,
      'description' => $this->description,
      'status' => $this->status,
      'successor' => $this->successor,
      'belongs_to' => $this->belongs_to,
    );

    $format = array(
      '%s',
      '%d',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
    );

    // Replace does not work properly (probably due to lack of auto increment).
    $exists_sql = $wpdb->prepare('SELECT uuid FROM ' . $table . ' WHERE uuid=%s', $this->uuid );
    $exists = $wpdb->get_col($exists_sql);
    if ( empty( $exists ) ) {
      $wpdb->insert( $table, $data, $format );
    }
    else {
      $where = array('uuid' => $this->uuid );
      $wpdb->update( $table, $data, $where, $format);
    }

  }

  public function json () {
    $outcome = array(
      'uuid' => $this->uuid,
      'title' => $this->title,
      'description' => $this->description,
      'status' => $this->status,
      'uri' => $this->uri,
      'successor' => empty($this->successor) ? '' : $this->successor,
      'belongs_to' => empty($this->belongs_to) ? '' : $this->belongs_to,
    );

    return $outcome;
  }


  public function delete() {
    global $wpdb;
    $table = $wpdb->prefix . 'outcomes_outcome';

    $wpdb->delete( $table, array('uuid' => $this->uuid) );
  }


  public function form() {
    $this->formHeader();
    $options = $this->getStatusOptions();
    $status = new Select();
    $status->id = 'status';
    $status->name = 'status';
    $status->label = __( 'Status' );
    $status->options = $this->getStatusOptions();
    $status->value = $this->status;
    $status->FormElement();

    $successor = new Select();
    $successor->id = 'successor';
    $successor->name = 'successor';
    $successor->label = __( 'Successor' );
    $successor->options = $this->getValidSuccessors();
    $successor->value = $this->successor;
    $successor->FormElement();

    $belongs = new Select();
    $belongs->id = 'belongs_to';
    $belongs->name = 'belongs_to';
    $belongs->label = __( 'Belongs To' );
    $belongs->options = $this->getValidCollections();
    $belongs->value = $this->belongs_to;
    $belongs->FormElement();

    $this->formFooter();
  }

  public function getStatusOptions() {
    return array(
      'draft' => __('Draft', 'candela_outcomes'),
      'active' => __('Active', 'candela_outcomes'),
      'deprecated' => __('Deprecated', 'candela_outcomes'),
    );
  }

  /**
   * Gets a list of valid successors for an outcome.
   */
  function getValidSuccessors() {
    global $wpdb;

    $options = array(
      '' => __('None', 'candela_outcomes'),
    );

    $outcome_table = $wpdb->prefix . 'outcomes_outcome';
    $query = $wpdb->prepare( 'SELECT uri, title FROM ' . $outcome_table  . ' WHERE uuid != %s ORDER BY title ', $this->uri );
    $outcomes = $wpdb->get_results( $query );

    if ( ! empty( $outcomes ) ) {
      foreach ($outcomes as $row => $values ) {
        $options[$values->uri] = $values->title;
      }
    }

    return $options;
  }

  /**
   * Gets a list of valid collections an outcome could belong to.
   */
  function getValidCollections() {
    global $wpdb;

    $options = array(
    );

    $collection_table = $wpdb->prefix . 'outcomes_collection';

    $collections = $wpdb->get_results('SELECT uri, title FROM ' . $collection_table  . ' ORDER BY title');

    if ( ! empty( $collections ) ) {
      foreach ($collections as $row => $values ) {
        $options[$values->uri] = $values->title;
      }
    }

    return $options;
  }

  public function validate( $nonce = TRUE ) {
    parent::validate( $nonce );

    $this->validateSuccessor();
    $this->validateBelongsTo();
  }

  public function validateSuccessor() {
    if ( ! empty ($this->successor ) ) {
      if ( ! $this->isValidUUID( $this->successor ) ) {
        $this->errors['successor']['invalid'] = __('Invalid successor UUID.', 'candela_outcomes');
      }
    }
  }

  public function validateBelongsTo() {
    if ( ! $this->isValidUUID( $this->belongs_to ) ) {
      $this->errors['belongs_to']['invalid'] = __('Invalid collection UUID for "belongs to".', 'candela_outcomes');
    }
  }

  public function uri( $edit = FALSE ) {
    // If URI is "external" just send that value.
    if ( $this->uri != NULL && $this->uri != home_url() . '/outcomes/outcome/' . $this->uuid) {
      return $this->uri;
    }
    if ( $edit ) {
      return home_url() . '/wp-admin/admin.php?page=edit_outcome&uuid=' . $this->uuid;
    }

    return home_url() . '/outcomes/outcome/' . $this->uuid;
  }

  public function processForm() {
    // Only process if valid nonce.
    if ( empty( $this->errors) ) {
      if ( ! empty ($_POST['uuid'] ) && empty( $this->uuid )) {
        $this->uuid = $_POST['uuid'];
      }
      else {
        // new collection
        $this->uuid = get_uuid();
      }

      $this->title = empty( $_POST['title']) ? '' : $_POST['title'];
      $this->description = empty( $_POST['description'] ) ? '' : $_POST['description'];
      $this->status = empty( $_POST['status'] ) ? '' : $_POST['status'];
      $this->successor = empty( $_POST['successor'] ) ? '' : $_POST['successor'];
      $this->belongs_to = empty( $_POST['belongs_to'] ) ? '' : $_POST['belongs_to'];
      $this->user_id = get_current_user_id();
      $this->uri = $this->uri();

      $this->validate();

      if ( empty ( $this->errors ) && ! empty( $_POST['submit'] ) ) {
        switch ( $_POST['submit'] ) {
          case 'Save':
            $this->save();
            wp_redirect( $this->uri( TRUE ) );
            exit();
            break;
          case 'Delete';
            if ( $this->status == 'draft' ) {
              // Add delete notification;
              $this->delete();
              wp_redirect( home_url() . '/wp-admin/admin.php?page=edit_collection' );
              exit();
            }
            error_admin_register( 'outcome', 'delete_unavailable', __('You can not delete outcomes that are not in the draft state.' ) );
            wp_redirect( $this->uri( TRUE) );
            exit;
            break;
        }
      }
    }
  }


  /**
   * Return TRUE if the outcome is active or deprecated, or if the user can
   * manage outcomes.
   *
   * This should likely be expanded to have an additional capability.
   */
  public function userCanView( $user = NULL ) {
    if ( $this->status == 'active' || $this->status == 'deprecated' ) {
      return TRUE;
    }

    if ( empty ( $user ) ) {
      $user = wp_get_current_user();
    }

    return user_can( $user, 'manage_outcomes');
  }

}

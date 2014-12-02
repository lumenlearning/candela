<?php

namespace Candela\Outcomes;

/**
 * Base class used to manage outcomes and collections.
 */
abstract class Base {
  public static $type = '';
  public $errors = array();
  public $uuid = '';
  public $user_id = 0;
  public $title = '';
  public $description = '';
  public $status = '';
  public $is_new = TRUE;

  abstract public function getStatusOptions();
  abstract public function load( $uuid );
  abstract public function save();
  abstract public function delete();
  abstract public function processForm();
  abstract public function uri( $edit = FALSE );

  public function hasErrors() {
    return ! empty( $this->errors );
  }

  public function formHeader() {
    print '<form class="form-horizontal" role="form" method="POST">';
    print '<input type="hidden" id="uuid" name="uuid" value="' . esc_attr( $this->uuid ) . '" >';
    wp_nonce_field( 'outcomes-edit', 'outcomes-edit-field' );

    // URI is auto filled.
    $title = new Text();
    $title->id = 'title';
    $title->name = 'title';
    $title->label = __('Title', 'candela_outcomes');
    $title->value = $this->title;
    $title->FormElement();

    if ( ! $this->is_new ) {
      print '<div id="edit-slug-box">';
      print '<strong>' . _e('Permalink') . ':</strong>';
      print '<span id="sample-permalink">' . esc_html( $this->uri() ) . '</span>';
      print '<span id="view-post-btn"><a href="' . esc_attr( $this->uri() ) . '" class="button button-small">' . __('View') . '</a></span>';
      print '<span id="edit-post-btn"><a href="' . esc_attr( $this->uri( TRUE ) ) . '" class="button button-small">' . __('Edit') . '</a></span>';
      print '</div>';
    }

    $description = new TextArea();
    $description->id = 'description';
    $description->name = 'description';
    $description->label = __('Description', 'candela_outcomes');
    $description->value = $this->description;
    $description->FormElement();
  }

  public function formFooter() {
    print '<div class="submitbox" id="submitpost">';
    print '<div id="saving-action">';
    print '<input type="submit" name="submit" id="save" class="button button-primary button-large" value="Save">';
    print '</div>';

    if ( !$this->is_new ) {
      print '<div id="delete-action">';
      print '<input type="submit" name="submit" id="delete" class="button button-primary button-large" value="Delete">';
      print '</div>';
    }
    print '</div>';
    print '</form>';
  }

  public function validate() {
    $this->validateNonce();
    $this->validateUuid();
    $this->validateUserID();
    $this->validateTitle();
    $this->validateDescription();
    $this->validateStatus();
  }

  public static function isValidUUID( $uuid ) {
    // uuid character
    $uc = '[a-f0-9A-F]';
    $regex = "/$uc{8}-$uc{4}-$uc{4}-$uc{4}-$uc{12}/";
    if ( preg_match( $regex, $uuid ) ) {
      return TRUE;
    }

    return FALSE;
  }

  public function isValidNonce() {
    if ( ! isset( $_POST['outcomes-edit-field'] ) || ! wp_verify_nonce( $_POST['outcomes-edit-field'], 'outcomes-edit' ) ) {
      return FALSE;
    }
    return TRUE;
  }

  public function validateNonce() {
    if ( ! $this->isValidNonce() ) {
      $this->errors['nonce']['invalid'] = __('Invalid Nonce.', 'candela_outcomes' );
    }
  }

  public function validateUuid() {
    if ( ! $this->isValidUUID( $this->uuid ) ) {
      $this->errors['uuid']['invalid'] = __('Invalid UUID.', 'candela_outcomes' );
    }
  }

  public function validateUserID() {
    if ( ! is_int( $this->user_id ) ) {
      $this->errors['user_id']['invalid'] = __('Invalid user id.', 'candela_outcomes' );
    }
  }

  public function validateStatus() {
    $valid = $this->getStatusOptions();

    if ( empty( $this->status ) ) {
      $this->errors['status']['empty'] = __('Empty status.', 'candela_outcomes');
    }
    else {
      if ( ! in_array( $this->status, array_keys( $valid ) ) ) {
        $this->errors['status']['invalid'] = __('Invalid status.', 'candela_outcomes');
      }
    }
  }

  public function validateTitle( ) {
    $this->validateGeneric( 'title', $this->title );
  }

  public function validateDescription( ) {
    $this->validateGeneric( 'description', $this->description );
  }

  public function validateGeneric( $field, $value ) {
    if ( empty( $value ) ) {
      $this->errors[$field]['empty'] = __('Empty ' . $field . '.', 'candela_outcomes');
    }
  }

  public function processFormErrors() {
    if ( ! empty( $this->errors ) ) {
      foreach ( $this->errors as $widget => $errors ) {
        print '<div class="error">';
        foreach ( $errors as $type => $message ) {
          print '<p>' . $message . '</p>';
        }
        print '</div>';
      }
    }
  }

  public function userCanEdit( $user = NULL ) {
    if ( empty ( $user ) ) {
      $user = wp_get_current_user();
    }

    return user_can( $user, 'manage_outcomes');
  }

}


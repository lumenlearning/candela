<?php
/**
 * Helper classes for HTML form elements.
 */
namespace Candela\Outcomes;

abstract class Widget {
  public $id = '';
  public $name = '';
  public $label = '';
  public $value = '';
  public $status = '';
  public $classes = array( 'control-group' );

  abstract public function Widget();

  public function EscClasses() {
    $cleaned = array();
    foreach ( $this->classes as $class ) {
      $cleaned = esc_attr( $class );
    }

    return $cleaned;
  }

  private function checkStatus() {
    switch ($this->status) {
      case 'success':
      case 'warning':
      case 'error':
        if ( ! in_array( $this->status, $this->classes ) ) {
          $this->classes[] = $this->status;
        }
        break;
      default;
        break;
    }
  }

  public function Label() {
    print '<label class="control-label" for="' . esc_attr( $this->id ) . '">';
    print $this->label;
    print '</label>';
  }

  public function FormElement() {
    $this->checkStatus();
    print '<div id="' . esc_attr( $this->id ) . '-div" class="' . $this->EscClasses() . '">';
    $this->Label();
    print '<div class="controls">';
    $this->Widget();
    print '</div>';
    print '</div>';
  }
}

class Text extends Widget {
  public function Widget() {
    print '<input type="text" id="' . esc_attr( $this->id ) . '" name="' . esc_attr( $this->name ) . '" value="' . esc_attr( $this->value ) . '">';
  }
}

class TextArea extends Widget {
  public function Widget() {
    print '<textarea class="widefat" rows="8" cols="10" id="' . esc_attr($this->id) . '" name="' . esc_attr($this->name) . '">' . esc_textarea( $this->value ) . '</textarea>';
  }
}

class Select extends Widget {
  public $options = array();

  public function Widget() {
    print '<select id="' . esc_attr( $this->id ) . '" name="' . esc_attr( $this->name ) . '" class="form-control">';
    $this->Options();
    print '</select>';
  }

  private function Options() {
    foreach ( $this->options as $value => $label ) {
      if ( $this->value == $value ) {
        $s = 'selected';
      }
      else {
        $s = '';
      }
      print '<option value="' . esc_attr($value) . "\" $s>" . esc_html($label) . '</option>';
    }
  }
}

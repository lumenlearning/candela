<?php
namespace CC;

class Base {
  function identifier($page, $prefix = "R_"){
    return $prefix . get_current_blog_id() . '_' . $page['ID'];
  }
}

?>
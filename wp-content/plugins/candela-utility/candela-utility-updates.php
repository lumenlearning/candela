<?php
namespace Candela\Utility\Upates;

define('CANDELA_UTILITY_VERBOSE' , 1);

$update = get_option( 'candela_utility_updates', '-1' );
echo $update;
switch ($update) {
  case '-1':
    update_000();
    break;
}

function update_000() {
  global $wpdb;

  define('CANDELA_UTILITY_VERBOSE', 0);
  // wp_get_sites() doesn't appear to work
  $blogs = $wpdb->get_results("SELECT blog_id, path FROM wp_blogs");

  $regexp = '(<iframe[^>]*src="([^"]+)"[^<]+</iframe>)';
  $first = TRUE;
  $urls = array();
  foreach ( $blogs as $blog ) {
    // switch_to_blog() doesn't appear to work via php-cli (restore blog() halts execution)
    $table = 'wp_' . $blog->blog_id . '_posts';
    $sql = 'SELECT ID, post_content FROM ' . $table . ' WHERE post_content LIKE \'%<iframe%\'';
    $posts = $wpdb->get_results($sql);
    foreach ( $posts as $post ) {
      $matches = array();
      preg_match_all($regexp, $post->post_content, $matches);

      if (empty($matches[0])) {
        print "Manual Review Blog($blog->blog_id) post: $post->ID " . get_post_link($blog, $post) . "\n";
      }
      else {
        $regexes = array(
          'youtube' => array(
            'regex' => '/embed\/([0-9a-zA-z\-\_]+)/',
            'rewrite' => 'https://www.youtube.com/watch?v=%%ID%%',
          ),
          'herokuapp' => array(
            'regex' => '/assessment_id=([0-9]+)/',
            'rewrite' => 'https://oea.herokuapp.com/assessments/%%ID%%',
          ),
          'openassessments' => array(
            'regex' => '/assessment_id=([0-9]+)/',
            'rewrite' => 'https://oea.herokuapp.com/assessments/%%ID%%',
          ),
          'vimeo' => array(
            'regex' => '/video\/([0-9]+)/',
            'rewrite' => 'https://vimeo.com/%%ID%%',
          ),
          'slideshare' => array(
            'regex' => '/embed_code\/([0-9]+)/',
            'rewrite' => 'https://www.slideshare.net/slideshow/embed_code/%%ID%%',
          ),
        );
        foreach ( $matches[1] as $i => $match ) {
          foreach ( $regexes as $domain => $info ) {
            if ( strpos( $match, $domain ) !== FALSE ) {
              $embed = array();
              preg_match( $info['regex'], $match, $embed );
              if ( ! empty( $embed[1] ) ) {
                $replace = str_replace( '%%ID%%',$embed[1], $info['rewrite'] );
                // ensure link is on its own line
                $replace = "\n" . $replace . "\n";

                // Replace original matched iframe block
                $post->post_content = str_replace($matches[0][$i], $replace, $post->post_content );
                if (CANDELA_UTILITY_VERBOSE) {
                  print "INFO: Replacing: " . $matches[0][$i] . "\n";
                  print "           with: " . trim($replace) . "\n";
                  print "         review: " . get_post_link($blog, $post) . "\n";
                  break;
                }
              }
              else {
                print "WARNING: Could not determine rewrite for blog(" . $blog->blog_id . ") post(" . $post->ID . "): " . $matches[0][$i] . "\n";
              }
            }
          }
        }
      }
      $wpdb->update( $table, array('post_content' => $post->post_content), array('ID' => $post->ID), array('%s'), array('%d') );
    }
  }
  update_option( 'candela_utility_updates', '0');
}

function get_post_link($blog, $post) {
  return 'https://courses.candelalearning.com' . $blog->path . 'wp-admin/post.php?post=' . $post->ID . '&action=edit';
}

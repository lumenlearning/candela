<?php
namespace Candela\Utility\Upates;

define('CANDELA_UTILITY_VERBOSE' , 1);

$update = get_option( 'candela_utility_updates', '-1' );

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

  $noaction = array();
  $updated = array();

  foreach ( $blogs as $blog ) {
    // switch_to_blog() doesn't appear to work via php-cli (restore blog() halts execution)
    $table = 'wp_' . $blog->blog_id . '_posts';
    $sql = 'SELECT ID, post_content, post_title FROM ' . $table . ' WHERE post_type != "revision" AND post_content LIKE \'%<iframe%\'';
    $posts = $wpdb->get_results($sql);
    foreach ( $posts as $post ) {
      $matches = array();
      preg_match_all($regexp, $post->post_content, $matches);

      if (empty($matches[0])) {
        $noaction[] = array(
          'blog' => $blog,
          'post' => $post,
        );
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
                $update[] = array(
                  'blog' => $blog,
                  'post' => $post,
                  'source' => $matches[0][$i],
                  'replace' => trim($replace),
                );
              }
              else {
                $noaction[] = array(
                  'blog' => $blog,
                  'post' => $post,
                  'unknown pattern' => $matches[0][$i],
                );
              }
            }
          }
        }
      }
      $wpdb->update( $table, array('post_content' => $post->post_content), array('ID' => $post->ID), array('%s'), array('%d') );
    }
  }
  update_option( 'candela_utility_updates', '0');
  output_details_000($update, $noaction);
}

function get_post_link($blog, $post) {
  return 'https://courses.candelalearning.com' . $blog->path . 'wp-admin/post.php?post=' . $post->ID . '&action=edit';
}

function output_details_000($update, $noaction) {
  // Output information
  if ( ! empty( $update ) ) {
    print "\n<h1>Updates</h1>\n";
    foreach ($update as $updated) {
      $title = esc_html($updated['post']->post_title);
      if (empty(trim($title))) {
        $title = 'Missing Post Title';
      }
      print '<h2><a href="' . get_post_link( $updated['blog'], $updated['post'] ) . '">' . $title . "</a></h2>\n";
      print "<h3>Source</h3>\n";
      print "<pre><code>\n" . esc_html($updated['source']) . "\n</code></pre>\n\n";
      print "<h4>Rewrite</h4>\n";
      print "<pre><code>\n" . esc_html($updated['replace']) . "\n</code></pre>\n\n";
    }
  }

  if ( ! empty( $noaction ) ) {
    print "\n<h1>Manual Review (no action applied)</h1>\n";
    foreach ($noaction as $review ) {
      $title = esc_html($review['post']->post_title);
      if ( empty(trim($title))) {
        $title = 'Missing Post Title';
      }
      print '<h2><a href="' . get_post_link( $review['blog'], $review['post'] ) . '">' . $title . "</a></h2>\n";
      print "<h3>Reason</h3>\n";
      if ( isset($review['unknown pattern' ] ) ) {
        print "<p>No known URL pattern</p>\n";
        print "<h4>Matching content</h4>\n";
        print "<pre><code>\n" . esc_html($review['unknown pattern']) . "\n</code></pre>\n\n";
      }
      else {
        print "<p>Content matched iframe tag but regex returned no match</p>\n";
      }
    }
  }
}


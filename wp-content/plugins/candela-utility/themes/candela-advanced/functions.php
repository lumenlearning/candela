<?php

/* ------------------------------------------------------------------------ *
 * Google Webfonts
 * ------------------------------------------------------------------------ */

function fitzgerald_enqueue_styles() {
	wp_enqueue_style( 'fitzgerald-fonts', 'http://fonts.googleapis.com/css?family=Crimson+Text:400,400italic,700|Roboto+Condensed:400,300,300italic,400italic' );
  wp_enqueue_style('foundation', get_stylesheet_directory_uri() . '/css/foundation.min.css');
  wp_enqueue_style('normalize', get_stylesheet_directory_uri() . '/css/normalize.css');
  wp_enqueue_style('custom', get_stylesheet_directory_uri() . '/css/custom.css');
}

function cadvanced_enqueue_scripts() {
  wp_enqueue_script('foundation', get_stylesheet_directory_uri() . '/js/foundation.min.js');
  wp_enqueue_script('deliverance', get_stylesheet_directory_uri() . '/js/deliverance.js', [], '', true);
  wp_enqueue_script('deliveryMan', get_stylesheet_directory_uri() . '/js/deliveryMan.js', [], '', true);
}

add_action( 'wp_print_styles', 'fitzgerald_enqueue_styles' );
add_action( 'wp_enqueue_scripts', 'cadvanced_enqueue_scripts' );

/**
 * Returns an html blog of meta elements
 *
 * @return string $html metadata
 */
function pbt_get_seo_meta_elements() {
	// map items that are already captured
	$meta_mapping = array(
	    'author' => 'pb_author',
	    'description' => 'pb_about_50',
	    'keywords' => 'pb_keywords_tags',
	    'publisher' => 'pb_publisher'
	);

	$html = "<meta name='application-name' content='PressBooks'>\n";
	$metadata = \PressBooks\Book::getBookInformation();

	// create meta elements
	foreach ( $meta_mapping as $name => $content ) {
		if ( array_key_exists( $content, $metadata ) ) {
			$html .= "<meta name='" . $name . "' content='" . $metadata[$content] . "'>\n";
		}
	}

	return $html;
}

function pbt_get_microdata_meta_elements() {
	// map items that are already captured
	$html = '';
	$micro_mapping = array(
	    'about' => 'pb_bisac_subject',
	    'alternativeHeadline' => 'pb_subtitle',
	    'author' => 'pb_author',
	    'contributor' => 'pb_contributing_authors',
	    'copyrightHolder' => 'pb_copyright_holder',
	    'copyrightYear' => 'pb_copyright_year',
	    'datePublished' => 'pb_publication_date',
	    'description' => 'pb_about_50',
	    'editor' => 'pb_editor',
	    'image' => 'pb_cover_image',
	    'inLanguage' => 'pb_language',
	    'keywords' => 'pb_keywords_tags',
	    'publisher' => 'pb_publisher',
	);
	$metadata = \PressBooks\Book::getBookInformation();

	// create microdata elements
	foreach ( $micro_mapping as $itemprop => $content ) {
		if ( array_key_exists( $content, $metadata ) ) {
			if ( 'pb_publication_date' == $content ) {
				$content = date( 'Y-m-d', $metadata[$content] );
			} else {
				$content = $metadata[$content];
			}
			$html .= "<meta itemprop='" . $itemprop . "' content='" . $content . "' id='" . $itemprop . "'>\n";
		}
	}

	return $html;
}

/**
 * Render Previous and next buttons
 *
 * @param bool $echo
 */
function ca_get_links($echo=true) {
  global $first_chapter, $prev_chapter, $next_chapter;
  $first_chapter = pb_get_first();
  $prev_chapter = pb_get_prev();
  $next_chapter = pb_get_next();
  if ($echo):
    ?><div class="nav">
    <?php if ($prev_chapter != '/') : ?>
    <span class="previous"><a href="<?php echo $prev_chapter; ?>"><?php _e('Previous Page', 'pressbooks'); ?></a></span>
  <?php endif; ?>
    <!-- 	<h2 class="entry-title"><?php the_title(); ?></h2> -->
    <?php if ($next_chapter != '/') : ?>
    <span class="next"><a href="<?php echo $next_chapter ?>"><?php _e('Next Page', 'pressbooks'); ?></a></span>
  <?php endif; ?>
    </div><?php
  endif;
}

/**
 * Sends a Window.postMessage to resize the iframe
 * (Only works in Canvas for now)
 */
function add_iframe_resize_message() {

  printf(
      '<script>
    // get rid of double iframe scrollbars
    var default_height = Math.max(
        document.body.scrollHeight, document.body.offsetHeight,
        document.documentElement.clientHeight, document.documentElement.scrollHeight,
        document.documentElement.offsetHeight);
    parent.postMessage(JSON.stringify({
        subject: "lti.frameResize",
        height: default_height
    }), "*");
</script>'
  );

}

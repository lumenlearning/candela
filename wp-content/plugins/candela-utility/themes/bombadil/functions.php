<?php

/* ------------------------------------------------------------------------ *
 * Styles and Google Webfonts
 * ------------------------------------------------------------------------ */

function bombadil_theme_styles() {
  wp_enqueue_style('foundation', get_stylesheet_directory_uri() . '/css/foundation.min.css');
  wp_enqueue_style('normalize', get_stylesheet_directory_uri() . '/css/normalize.css');
  wp_enqueue_style('style', get_stylesheet_directory_uri() . '/style.css');
}
add_action( 'wp_print_styles', 'bombadil_theme_styles' );

function bombadil_theme_scripts() {
  wp_enqueue_script('foundation', get_stylesheet_directory_uri() . '/js/foundation.min.js', array('jquery'), '', true);
  wp_enqueue_script('iframe_resizer', get_stylesheet_directory_uri() . '/js/iframe_resizer.js', array('jquery'), '', true);
  wp_enqueue_script('embedded_audio', get_stylesheet_directory_uri() . '/js/audio_behavior.js', array('jquery'), '', true);
}
add_action( 'wp_enqueue_scripts', 'bombadil_theme_scripts' );


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
    ?><div class="bottom-nav-buttons">
    <?php if ($prev_chapter != '/') : ?>
    <a class="page-nav-btn" id="prev" href="<?php echo $prev_chapter; ?>"><?php _e('Previous', 'pressbooks'); ?></a>
  <?php endif; ?>
    <?php if ($next_chapter != '/') : ?>
    <a class="page-nav-btn" id="next" href="<?php echo $next_chapter; ?>"><?php _e('Next', 'pressbooks'); ?></a>
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
    if(self != top){
      // get rid of double iframe scrollbars
      var default_height = Math.max(
          document.body.scrollHeight, document.body.offsetHeight,
          document.documentElement.clientHeight, document.documentElement.scrollHeight,
          document.documentElement.offsetHeight);
      parent.postMessage(JSON.stringify({
          subject: "lti.frameResize",
          height: default_height
      }), "*");
    }
</script>'
  );

}

// allow iframe tag within posts
function allow_post_tags( $allowedposttags ){
    $allowedposttags['iframe'] = array(
        'align' => true,
        'allowFullScreen' => true,
        'class' => true,
        'frameborder' => true,
        'height' => true,
        'id' => true,
        'longdesc' => true,
        'marginheight' => true,
        'marginwidth' => true,
        'mozallowfullscreen' => true,
        'name' => true,
        'sandbox' => true,
        'seamless' => true,
        'scrolling' => true,
        'src' => true,
        'srcdoc' => true,
        'style' => true,
        'width' => true,
        'webkitAllowFullScreen' => true
    );
    return $allowedposttags;
}
add_filter('wp_kses_allowed_html','allow_post_tags', 1);


// Where to call this function...
  $navigation = get_option( 'pressbooks_theme_options_navigation' );
  $header_preference = should_show_content_only();
// error_log(print_r($navigation['navigation_show_header_and_search']));
// error_log(print_r($navigation['navigation_show_search_only']));
      if (!isset($navigation['navigation_show_header_and_search']) && !isset($navigation['navigation_show_search_only'])) {
        $header_preference = should_show_header();
        } elseif (isset($navigation['navigation_show_header_and_search'] )) {
        // <!-- (should_show_content_only()); -->
        // } elseif ($navigation['navigation_show_search_only'] == 1) {
        // // <!-- (should_show_search_only()); -->
        }
      return $header_preference;


function should_show_header(){
  return (!isset($_GET['content_only']) || !isset($_GET['hide_search']));
}  // works in header.php

function should_show_search_only(){
  $navigation = get_option( 'pressbooks_theme_options_navigation' );
  if ($navigation['navigation_show_search_only'] == 1) {
    return (isset($_GET['content_only']) && !isset($_GET['hide_search']));
  }
}  // works in header.php

function should_show_content_only(){
    return (!isset($_GET['content_only']) && !isset($_GET['hide_search']));
}  // works in header.php, but this one doesn't make sense to me- maybe due to lack of sleep
/* ------------------------------------------------------------------------ *
 * Navigation Options Tab
 * ------------------------------------------------------------------------ */

// Navigation Options Registration
function pressbooks_theme_options_navigation_init() {

	$_page = $_option = 'pressbooks_theme_options_navigation';
	$_section = 'navigation_options_section';
	$defaults = array(
		'navigation_show_header_and_search' => 0,
		'navigation_show_search_only' => 1
	);

	if ( false == get_option( $_option ) ) {
		add_option( $_option, $defaults );
	}
error_log("setup options");
error_log(print_r(get_option( $_option ),true));
	add_settings_section(
		$_section,
		__( 'Navigation Options', 'pressbooks' ),
		'pressbooks_theme_options_navigation_callback',
		$_page
	);

	add_settings_field(
		'navigation_show_header_and_search',
		__( 'Show Header and Search Bar', 'pressbooks' ),
		'pressbooks_theme_navigation_show_header_and_search_callback',
		$_page,
		$_section,
		array(
			 __( 'Enable Full Header with Search Bar', 'pressbooks' ),
		)
	);

	add_settings_field(
		'navigation_show_search_only',
		__( 'Show Search Only', 'pressbooks' ),
		'pressbooks_theme_navigation_show_search_only_callback',
		$_page,
		$_section,
		array(
			 __( 'Enable Only Search Bar', 'pressbooks' )
		)
	);

	register_setting(
		$_option,
		$_option,
		'pressbooks_theme_options_navigation_sanitize'
	);
}
add_action( 'admin_init', 'pressbooks_theme_options_navigation_init' );


// Navigation Options Section Callback
function pressbooks_theme_options_navigation_callback() {
	echo '<p>' . __( 'These options apply to navigation view.', 'pressbooks' ) . '</p>';
}

// Navigation Options Field Callbacks
function pressbooks_theme_navigation_show_header_and_search_callback( $args ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	if ( ! isset( $options['navigation_show_header_and_search'] ) ) {
		$options['navigation_show_header_and_search'] = 0;
	}
error_log("callback options");
error_log(print_r($options,true));
	$html = '<input type="checkbox" id="navigation_show_header_and_search" name="pressbooks_theme_options_navigation[navigation_show_header_and_search]" value="1"' . checked( 1, $options['navigation_show_header_and_search'], false ) . '/> ';
	$html .= '<label for="navigation_show_header_and_search">' . $args[0] . '</label><br />';
	echo $html;
}
function pressbooks_theme_navigation_show_search_only_callback( $args ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	if ( ! isset( $options['navigation_show_search_only'] ) ) {
		$options['navigation_show_search_only'] = 0;
	}
	$html = '<input type="checkbox" id="navigation_show_search_only" name="pressbooks_theme_options_navigation[navigation_show_search_only]" value="1"' . checked( 1, $options['navigation_show_search_only'], false ) . '/> ';
	$html .= '<label for="navigation_show_search_only">' . $args[0] . '</label><br />';
	echo $html;
}

// Navigation Options Input Sanitization
function pressbooks_theme_options_navigation_sanitize( $input ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	// Absint
	foreach ( array( 'navigation_show_header_and_search' ) as $val ) {
		if ( ! isset( $input[$val] ) || $input[$val] != '1' ) $options[$val] = 0;
		else $options[$val] = 1;
	}

	// Checkmarks
	foreach ( array( 'navigation_show_search_only' ) as $val ) {
		if ( ! isset( $input[$val] ) || $input[$val] != '1' ) $options[$val] = 0;
		else $options[$val] = 1;
	}

	return $options;
}
// it ends here



// // kelly faking it to create theme options page
// function bombadil_register_settings() {
//   register_setting( 'bombadil_theme_options', 'bombadil_options', 'bombadil_validate_options' );
// }
//
// add_action( 'admin_init', 'bombadil_register_settings' );
//
// $settings = get_option( 'bombadil_options', $bombadil_options );
//
// $bombadil_options = array (
//   'default_no_header' =>  "get_bloginfo('wpurl') . '/' . $page . '?hide_search'",
//   'show_header' => "get_bloginfo('wpurl') . '/' . $page . '/'",
//   'show_search' => "get_bloginfo('wpurl') . '/' . $page . '/'",
//  );
//
// // or something using this kind of thing?
// $bombadil_header = array(
//   'default' => array(
//     'value' => "get_bloginfo('wpurl') . '/' . $page . '?hide_search'",
//     'label' => 'Content Only'
//   ),
//   'show_header' => array(
//     'value' => "get_bloginfo('wpurl') . '/' . $page . '/'",
//     'label' => 'Show Header'
//
//   ),
//   'show_search' => array(
//     'value' => "get_bloginfo('wpurl') . '/' . $page . '/'",
//     'label' => 'Show Search Bar'
//   )
// );

// or would you assign a value of 1, 2, 3 for the options then say if that value, then wpurl= the blogurl we want?

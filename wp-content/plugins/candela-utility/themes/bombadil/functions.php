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


// to show appropriate header parts
function show_nav_container(){
  $navigation = get_option( 'pressbooks_theme_options_navigation' );
  if (($navigation['navigation_show_header'] == 1) || ($navigation['navigation_show_search'] == 1)) {
    return true;
  }
}

function show_nav_options($selected_option){
  $via_LTI_launch = isset($_GET['content_only']);
  if($via_LTI_launch){
    $navigation = get_option( 'pressbooks_theme_options_navigation' );
    if ($navigation[$selected_option] == 1) {
      return true;
    } else {
      return false;
    }
  } else {
    return true;
  }
}

function show_header(){
    return show_nav_options('navigation_show_header');
}

function show_header_link(){
    return show_nav_options('navigation_show_header_link');
}

function show_search(){
    return show_nav_options('navigation_show_search');
}

function show_small_title(){
    return show_nav_options('navigation_show_small_title');
}

function show_edit_button(){
    return show_nav_options('navigation_show_edit_button');
}

function show_navigation_buttons(){
    return show_nav_options('navigation_show_navigation_buttons');
}
/* ------------------------------------------------------------------------ *
 * Navigation Options Tab
 * ------------------------------------------------------------------------ */

// Navigation Options Registration
function pressbooks_theme_options_navigation_init() {

	$_page = $_option = 'pressbooks_theme_options_navigation';
	$_section = 'navigation_options_section';
	$defaults = array(
		'navigation_show_header' => 0,
    'navigation_show_header_link' => 0,
		'navigation_show_search' => 1,
    'navigation_show_small_title' => 0,
    'navigation_show_edit_button' => 1,
    'navigation_show_navigation_buttons' => 0,
    'navigation_show_waymaker_logo' => 0,
    'navigation_hide_logo' => 0

	);

	if ( false == get_option( $_option ) ) {
		add_option( $_option, $defaults );
	}

	add_settings_section(
		$_section,
		__( 'Navigation Options', 'pressbooks' ),
		'pressbooks_theme_options_navigation_callback',
		$_page
	);

	add_settings_field(
		'navigation_show_header',
		__( 'Header', 'pressbooks' ),
		'pressbooks_theme_navigation_show_header_callback',
		$_page,
		$_section,
		array(
			 __( 'Display Header Bar with Course Title', 'pressbooks' ),
		)
	);

  	add_settings_field(
  		'navigation_show_header_link',
  		__( 'Course Title Link', 'pressbooks' ),
  		'pressbooks_theme_navigation_show_header_link_callback',
  		$_page,
  		$_section,
  		array(
  			 __( 'Make Course Title a Clickable Link to Table of Contents (Header must be selected)', 'pressbooks' ),
  		)
  	);

	add_settings_field(
		'navigation_show_search',
		__( 'Search', 'pressbooks' ),
		'pressbooks_theme_navigation_show_search_callback',
		$_page,
		$_section,
		array(
			 __( 'Enable Search Bar', 'pressbooks' )
		)
	);

  add_settings_field(
    'navigation_show_small_title',
    __( 'Part Title', 'pressbooks' ),
		'pressbooks_theme_navigation_show_small_title_callback',
		$_page,
		$_section,
		array(
			 __( 'Display Part/Module/Chapter Title', 'pressbooks' )
		)
  );

  add_settings_field(
    'navigation_show_edit_button',
    __( 'Edit Button', 'pressbooks' ),
		'pressbooks_theme_navigation_show_edit_button_callback',
		$_page,
		$_section,
		array(
			 __( 'Enable Edit Button', 'pressbooks' )
		)
  );

  add_settings_field(
    'navigation_show_navigation_buttons',
    __( 'Navigation Buttons', 'pressbooks' ),
		'pressbooks_theme_navigation_show_navigation_buttons_callback',
		$_page,
		$_section,
		array(
			 __( 'Enable Navigation Buttons', 'pressbooks' )
		)
  );

  add_settings_field(
    'navigation_show_waymaker_logo',
    __( 'Waymaker Logo', 'pressbooks' ),
		'pressbooks_theme_navigation_show_waymaker_logo_callback',
		$_page,
		$_section,
		array(
			 __( 'Enable Waymaker Logo (Candela Logo is default)', 'pressbooks' )
		)
  );

  add_settings_field(
    'navigation_hide_logo',
    __( 'Hide Footer Logo', 'pressbooks' ),
    'pressbooks_theme_navigation_hide_logo_callback',
    $_page,
    $_section,
    array(
       __( 'Hide Footer Logo', 'pressbooks' )
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
	echo '<p>' . __( 'These options allow customization of the page navigation and are only available when logged in via LTI launch.', 'pressbooks' ) . '</p>';
}

// Navigation Options Field Callback
function pressbooks_theme_navigation_show_header_callback( $args ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	if ( ! isset( $options['navigation_show_header'] ) ) {
		$options['navigation_show_header'] = 0;
	}
	$html = '<input type="checkbox" id="navigation_show_header" name="pressbooks_theme_options_navigation[navigation_show_header]" value="1"' . checked( 1, $options['navigation_show_header'], false ) . '/> ';
	$html .= '<label for="navigation_show_header">' . $args[0] . '</label><br />';
	echo $html;
}

// Navigation Options Field Callback
function pressbooks_theme_navigation_show_header_link_callback( $args ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	if ( ! isset( $options['navigation_show_header_link'] ) ) {
		$options['navigation_show_header_link'] = 0;
	}
	$html = '<input type="checkbox" id="navigation_show_header_link" name="pressbooks_theme_options_navigation[navigation_show_header_link]" value="1"' . checked( 1, $options['navigation_show_header_link'], false ) . '/> ';
	$html .= '<label for="navigation_show_header_link">' . $args[0] . '</label><br />';
	echo $html;
}

// Navigation Options Field Callback
function pressbooks_theme_navigation_show_search_callback( $args ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	if ( ! isset( $options['navigation_show_search'] ) ) {
		$options['navigation_show_search'] = 0;
	}
	$html = '<input type="checkbox" id="navigation_show_search" name="pressbooks_theme_options_navigation[navigation_show_search]" value="1"' . checked( 1, $options['navigation_show_search'], false ) . '/> ';
	$html .= '<label for="navigation_show_search">' . $args[0] . '</label><br />';
	echo $html;
}

// Navigation Options Field Callback
function pressbooks_theme_navigation_show_small_title_callback( $args ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	if ( ! isset( $options['navigation_show_small_title'] ) ) {
		$options['navigation_show_small_title'] = 1;
	}
	$html = '<input type="checkbox" id="navigation_show_small_title" name="pressbooks_theme_options_navigation[navigation_show_small_title]" value="1"' . checked( 1, $options['navigation_show_small_title'], false ) . '/> ';
	$html .= '<label for="navigation_show_small_title">' . $args[0] . '</label><br />';
	echo $html;
}

// Navigation Options Field Callback
function pressbooks_theme_navigation_show_edit_button_callback( $args ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	if ( ! isset( $options['navigation_show_edit_button'] ) ) {
		$options['navigation_show_edit_button'] = 1;
	}
	$html = '<input type="checkbox" id="navigation_show_edit_button" name="pressbooks_theme_options_navigation[navigation_show_edit_button]" value="1"' . checked( 1, $options['navigation_show_edit_button'], false ) . '/> ';
	$html .= '<label for="navigation_show_edit_button">' . $args[0] . '</label><br />';
	echo $html;
}

function pressbooks_theme_navigation_show_navigation_buttons_callback( $args ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	if ( ! isset( $options['navigation_show_navigation_buttons'] ) ) {
		$options['navigation_show_navigation_buttons'] = 0;
	}
	$html = '<input type="checkbox" id="navigation_show_navigation_buttons" name="pressbooks_theme_options_navigation[navigation_show_navigation_buttons]" value="1"' . checked( 1, $options['navigation_show_navigation_buttons'], false ) . '/> ';
	$html .= '<label for="navigation_show_navigation_buttons">' . $args[0] . '</label><br />';
	echo $html;
}

function pressbooks_theme_navigation_show_waymaker_logo_callback( $args ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	if ( ! isset( $options['navigation_show_waymaker_logo'] ) ) {
		$options['navigation_show_waymaker_logo'] = 0;
	}
	$html = '<input type="checkbox" id="navigation_show_waymaker_logo" name="pressbooks_theme_options_navigation[navigation_show_waymaker_logo]" value="1"' . checked( 1, $options['navigation_show_waymaker_logo'], false ) . '/> ';
	$html .= '<label for="navigation_show_waymaker_logo">' . $args[0] . '</label><br />';
	echo $html;
}

function pressbooks_theme_navigation_hide_logo_callback( $args ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	if ( ! isset( $options['navigation_hide_logo'] ) ) {
		$options['navigation_hide_logo'] = 0;
	}
	$html = '<input type="checkbox" id="navigation_hide_logo" name="pressbooks_theme_options_navigation[navigation_hide_logo]" value="1"' . checked( 1, $options['navigation_hide_logo'], false ) . '/> ';
	$html .= '<label for="navigation_hide_logo">' . $args[0] . '</label><br />';
	echo $html;
}

// Navigation Options Input Sanitization
function pressbooks_theme_options_navigation_sanitize( $input ) {

	$options = get_option( 'pressbooks_theme_options_navigation' );

	// Checkmarks
	foreach ( array( 'navigation_show_header' ) as $val ) {
		if ( ! isset( $input[$val] ) || $input[$val] != '1' ) $options[$val] = 0;
		else $options[$val] = 1;
	}

  foreach ( array( 'navigation_show_header_link' ) as $val ) {
		if ( ! isset( $input[$val] ) || $input[$val] != '1' ) $options[$val] = 0;
		else $options[$val] = 1;
	}

	foreach ( array( 'navigation_show_search' ) as $val ) {
		if ( ! isset( $input[$val] ) || $input[$val] != '1' ) $options[$val] = 0;
		else $options[$val] = 1;
	}

  foreach ( array( 'navigation_show_small_title' ) as $val ) {
    if ( ! isset( $input[$val] ) || $input[$val] != '1' ) $options[$val] = 0;
    else $options[$val] = 1;
  }

  foreach ( array( 'navigation_show_edit_button' ) as $val ) {
    if ( ! isset( $input[$val] ) || $input[$val] != '1' ) $options[$val] = 0;
    else $options[$val] = 1;
  }

  foreach ( array( 'navigation_show_navigation_buttons' ) as $val ) {
    if ( ! isset( $input[$val] ) || $input[$val] != '1' ) $options[$val] = 0;
    else $options[$val] = 1;
  }

  foreach ( array( 'navigation_show_waymaker_logo' ) as $val ) {
    if ( ! isset( $input[$val] ) || $input[$val] != '1' ) $options[$val] = 0;
    else $options[$val] = 1;
  }

  foreach ( array( 'navigation_hide_logo' ) as $val ) {
    if ( ! isset( $input[$val] ) || $input[$val] != '1' ) $options[$val] = 0;
    else $options[$val] = 1;
  }

  return $options;
  }

  function choose_logo($chosen_logo){
      $navigation = get_option( 'pressbooks_theme_options_navigation' );
      if ((isset($navigation[$chosen_logo]) && ($navigation[$chosen_logo] == 1))) {
        return true;
      } else {
        return false;
      }
  }

// Footer logo options
  function show_waymaker_logo(){
      return choose_logo('navigation_show_waymaker_logo');
  }

  // not currently being called
  function show_logo(){
      return !choose_logo('navigation_hide_logo');
  }

/**
 * WordPress plugin.
 */
jQuery(document).ready(function($) {
  (function() {
    function in_iframe() {
      try {
        return window.self !== window.top;
      } catch (e) {
        return true;
      }
    }
    function new_window() {
      // Only new window if in iframe.
      if ( !in_iframe() ) {
        return false;
      }

      return true;
    }

    function link_is_local(href) {
      host = $(location).attr('protocol') + "//" + $(location).attr('host');
      if (href.indexOf('http') >= 0 || href.indexOf('https') >= 0) {
        // Link is absolute
        if (href.indexOf(host) >= 0) {
          return true;
        }

        // assume not local
        return false;
      }
      else {
        // link is relative
        return true;
      }

      return false;
    }

    function link_new_window( href ) {
      if ( href.indexOf('wp-admin') >= 0 || // link is to an admin page
          $(location).attr('href').indexOf('wp-admin') >= 0 ||
          !link_is_local(href) // link is on an admin page
      ) {
        window.open(href);
        return false;
      }
      // provide default behavior
      return true;
    }

    // execute our link rewrite
    if ( new_window() ) {
      $('a').live('click', 'a', function() {
        return link_new_window( $(this).attr('href') );
      });
    }
  })();
});


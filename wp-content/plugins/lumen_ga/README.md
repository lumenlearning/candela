##Plugin Name##
Plugin Name: Candela Analytics
Plugin URI: http://lumenlearning.com/
Description: Adds Google Analyics tracking code to the theme header
Version: 0.1
Author URI: http://lumenlearning.com
License URI: http://www.gnu.org/licenses/gpl-2.0.html

##Install Directions

Enable the plugin to have Google Analytics code added to the header of the theme.

Then set a few configuration constants in your wp-config.php file.

    define('LUMEN_GA_WEB_PROPERTY_ID', 'UA-XXXX-Y');
    define('LUMEN_GA_COOKIE_DOMAIN', 'auto');

For testing use cookie domain, `none`.

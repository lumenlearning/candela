<?php
/*
Plugin Name: Candela Thin Exports
Description: A simple plugin to export Pressbooks books as thin cartridges with deep links to each page.
Version: 0.1
Author: Lumen Learning
Author URI: http://lumenlearning.com
*/
?>
<?php
require_once('thincc_manage.php');
require_once('cc/manifest.php');

register_activation_hook(__FILE__, 'install_thin_exports');
function install_thin_exports()
{
  if (version_compare(get_bloginfo('version'), '4.0', '<')) {
    deactivate_plugins(basename(__FILE__));
  }
}

add_action('admin_menu', 'thincc_admin_page');
function thincc_admin_page()
{
  $plugin_page = add_management_page('Export to ThinCC', 'Export to ThinCC', 'export', basename(__FILE__), 'thincc_manage');
  add_action('load-' . $plugin_page, 'thincc_add_js');
}

if (isset($_POST['download'])) {

  add_action('wp_loaded', 'thin_cc_download', 1);
  function thin_cc_download()
  {
    thincc_ajax();
    die();
  }
}

add_action('wp_ajax_thincc_ajax', 'thincc_ajax');
function thincc_ajax()
{
  $sitename = sanitize_key(get_bloginfo('name'));
  if (!empty($sitename)) $sitename .= '.';
  $filename = $sitename . 'wordpress.' . date('Y-m-d');

  if(isset($_POST['download']) && $_POST['download'] == '0') {
    $manifest = new \CC\Manifest(\PressBooks\Book::getBookStructure(), ['version' => 'thin', 'inline' => true]);
    $manifest->build_manifest();

    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename=' . $filename . '.xml');
    header('Content-Type: text/plain; charset=' . get_option('blog_charset'), true);

    echo '<pre>', htmlentities($manifest), '</pre>';
  } else {
    $manifest = new \CC\Manifest(\PressBooks\Book::getBookStructure(), ['version' => '1.2', 'inline' => false]);
    $manifest->build_manifest();
    $file = $manifest->build_zip();

    header('Content-Type: application/vnd.ims.imsccv1p2+application/zip');
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: attachment; filename="' . $filename . '.imscc"');
    readfile($file);
  }
}

?>
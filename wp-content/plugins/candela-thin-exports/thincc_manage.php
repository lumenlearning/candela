<?php

function thincc_add_js()
{
  wp_enqueue_style('thin_cc_css', plugins_url('thincc.css', __FILE__));
  wp_enqueue_script('thin_cc_js', plugins_url('thincc.js', __FILE__));

  $protocol = isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';
  $params = array(
      'ajaxurl' => admin_url('admin-ajax.php', $protocol)
  );
  wp_localize_script('thin_cc_js', 'thin_cc_js', $params);

}

function thincc_manage()
{
if (!current_user_can('export'))
wp_die(__('You do not have sufficient permissions to export the content of this site.'));

global $wpdb;
?>

<div class="thincc">
  <div class="wrap">

    <h2>Export to Thin Common Cartridge</h2>

    <div id="main">

      <form id="thincc-form" action="" method="post">
          <div class="submit">
            <input type="hidden" name="download" value="<?php echo get_home_path(); ?>"/>

            <a href="#" class="button-secondary">Preview Thin-CC</a>
            <input class="button button-primary" type="submit" value="Download CC 1.2 .imscc" name="submit">
          </div>
      </form>

      <div id="thincc_modal">
        <div id="thincc-results-close-holder"><a href="#" id="thincc-results-close">Close</a></div>
        <div id="thincc-results">Results</div>
      </div>

    </div>
  </div>
</div>


<?php
}
?>
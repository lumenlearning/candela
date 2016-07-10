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

<div class="thincc" xmlns="http://www.w3.org/1999/html">
  <div class="wrap">

    <h2>Export to Thin Common Cartridge</h2>

    <div id="main">

      <form id="thincc-form" action="" method="post">

          <div class="options">
            <div><input name="export_flagged_only" id="export_only" type="checkbox" checked/><label for="export_only">Only pages marked as export</label></div>
            <div><input name="use_custom_vars" id="use_custom_vars" type="checkbox" /><label for="use_custom_vars">Sakai compatibility (use custom_param instead of query param)</label></div>
            <div><input name="use_web_links" id="use_web_links" type="checkbox" /><label for="use_web_links">Use normal web links instead of LTI links</label></div>
            <div><input name="include_fm" id="include_fm" type="checkbox" /><label for="include_fm">Include Front Matter</label></div>
            <div><input name="include_bm" id="include_bm" type="checkbox" /><label for="include_bm">Include Back Matter</label></div>
            <div><input name="include_parts" id="include_parts" type="checkbox" /><label for="include_parts">Include links to Parts</label></div>
            <div><input name="include_topics" id="include_topics" type="checkbox" /><label for="include_topics">Create Discussion Topics (for pages starting with "Discussion:")</label></div>
            <div><input name="include_assignments" id="include_assignments" type="checkbox" /><label for="include_assignments">Create Assignments (for pages starting with "Assignment:")</label></div>
            <div><input name="include_guids" id="include_guids" type="checkbox" /><label for="include_guids">Include GUIDs</label></div>

            <div><label for="cc_version_selector">CC Version:</label>
              <select id="cc_version_selector" name="version">
                <option value="1.1">1.1 (All LMSs)</option>
                <option value="1.2">1.2 (Bb/Sakai/Canvas)</option>
                <option value="1.3" selected>1.3 (Canvas/Sakai)</option>
                <option value="thin">Thin-CC (1.3) (Canvas)</option>
              </select>
            </div>
          </div>

          <div class="submit">
            <input type="hidden" name="cc_download" value="<?php echo get_home_path(); ?>"/>

            <a href="#" class="button-secondary">Preview Flat-CC</a>
            <input class="button button-primary" type="submit" value="Download CC 1.1 .imscc" name="submit">
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

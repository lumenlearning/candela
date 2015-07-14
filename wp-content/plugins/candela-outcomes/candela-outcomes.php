<?php
/**
* @wordpress-plugin
* Plugin Name:       Candela Outcomes
* Description:       Add Outcomes meta field for coursework
* Version:           0.1
* Author:            Lumen Learning
* Author URI:        http://lumenlearning.com
* Text Domain:       lti
* License:           MIT
* GitHub Plugin URI: https://github.com/lumenlearning/candela-outcomes
*/

namespace Candela\Outcomes;
if( ! defined('CANDELA_OUTCOMES_GUID')){
    define('CANDELA_OUTCOMES_GUID', 'CANDELA_OUTCOMES_GUID');
}

add_action('admin_init', '\Candela\Outcomes\candela_on_admin_init');

//Initialize
function candela_on_admin_init() {
$types = array( 'back-matter', 'chapter', 'front-matter',);
    foreach($types as $type){
        add_meta_box('outcomes',
        __('Course Outcomes', 'textdomain'),
        '\Candela\Outcomes\outcomes_metabox_render',
        $type,
        'normal',
        'high'
        );
    }
}

//Render fields
function outcomes_metabox_render($post) {
    $data = get_post_meta($post->ID, CANDELA_OUTCOMES_GUID, true);
    ?>
    <div class="inside">
        <label for="outcomes_input"><?php _e( "List GUID(s) associated with this content. Separate multiple GUIDs with commas.", 'textdomain' ); ?></label>
        <input id="outcomes_input" class="widefat" type="text" name="candela_outcomes_guid_data" placeholder="ie. 26e0522b-abe5-4659-b393-c139f8acf97d" pattern="[a-zA-Z0-9, :-]*" value="<?php echo (isset($data)) ? esc_attr($data) : ''; ?>"/>
    </div>
    <?php
}


//Update metadata when user saves post
add_action('wp_insert_post', '\Candela\Outcomes\candela_outcomes_save_meta_value', 10, 2);

function candela_outcomes_save_meta_value($id) {
    if(isset($_POST['candela_outcomes_guid_data']))
    $outcomes_input = strtolower($_POST['candela_outcomes_guid_data']);

    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
    return $id;
    if (!current_user_can('edit_posts'))
    return;

    if(isset($outcomes_input))
    $outcomes_input = preg_replace('/([^a-z0-9, :-])/', '', $outcomes_input);

    if (!isset($id))
    $id = (int) $_REQUEST['post_ID'];

    if (isset($outcomes_input)) {
        update_post_meta($id, CANDELA_OUTCOMES_GUID, $outcomes_input);
    } else {
        delete_post_meta($id, CANDELA_OUTCOMES_GUID);
    }
}

//Display outcome html
add_action('display_outcome_html', '\Candela\Outcomes\outcome_display_html', 10, 1);

function outcome_display_html($id){

    $dataguid = get_post_meta($id, CANDELA_OUTCOMES_GUID);
    if(!empty($dataguid)){
    ?>
    <div id='outcome_description' style='display:none'
        data-outcome-guids='<?php print(esc_attr($dataguid[0])); ?>'>
    </div>
    <?php
    }
}

//Display user lti html
add_action('display_user_lti_id', '\Candela\Outcomes\display_user_id_html', 10, 1);

function display_user_id_html( $user_id ) {
    switch_to_blog(1);
    $external_id = get_user_meta( $user_id, 'candelalti_external_userid', TRUE );
    restore_current_blog();
    if(!empty($external_id)){
    ?>
        <div id="lti_user_id" style='display:none'
          data-user-id='<?php print $external_id; ?>'>
        </div>
    <?php
    }
}

//Add Candela Outcomes to import meta
  add_filter( 'pb_import_metakeys', 'get_import_metakeys' );

  function get_import_metakeys( $fields ) {
      $fields[] = 'CANDELA_OUTCOMES_GUID';
      return $fields;
  }

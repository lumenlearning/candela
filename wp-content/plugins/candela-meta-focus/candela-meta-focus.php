<?php
/**
* @wordpress-plugin
* Plugin Name:       Candela Meta Focus-Level
* Description:       The degree of focus appropriate for this content.
* Version:           0.1
* Author:            Lumen Learning
* Author URI:        http://lumenlearning.com
* Text Domain:       lti
* License:           MIT
* GitHub Plugin URI: https://github.com/lumenlearning/candela/
*/



/***********  ACTION: INSTANCIATE, THEN TRIGGER METHODS *****/
function instantiate_FocusRating() {
    new FocusRating();
}
if ( is_admin() ) {
    add_action( 'load-post.php', 'instantiate_FocusRating' );
    add_action( 'load-post-new.php', 'instantiate_FocusRating' );
}

/***********  THE MEAT  *****/
class FocusRating{
    /** Hook into the appropriate actions when the class is constructed. */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		//add_action( 'save_post', array( $this, 'save' ) ); /* WP's example. */
	}

    /***********  ADD METABOX  *****/
	public function add_meta_box( $post_type ) {
        $post_types = array('back-matter', 'chapter', 'front-matter');
        if ( in_array( $post_type, $post_types )) {
//error_log($post_type . ' IS IN THE ARRAY!');
            add_meta_box('focus', //(ID, title, callback, screen, context, priority, cb args)
            __('Focus Level', 'textdomain'),
            array($this, 'focus_metabox_render'),
            $post_type,
            'normal',
            'high'
            );
        }
	}

    /***********  RENDER METABOX (StackO Q#13903529 / SmashingMag mashup)  *****/
    public function focus_metabox_render($post) {
        $data = get_post_meta($post->ID, 'candela_focus_guid', true);
        ?>
        <div class="inside">
            <label for="focus_select"><?php _e( "Set the level of focus appropriate for this content.", 'textdomain' ); ?></label>
            <select id="focus_select" class="select" name="focus_select">
                <option value="high">High</option>
                <option value="normal">Normal</option>
                <option value="skim">Skim</option>
            </select>
        </div>
        <?php
    }

    /*********** SAVE *****/
    public function save_focus_meta($id) {
        //Update metadata when user saves post
        add_action('wp_insert_post', 'save_focus_meta');
        // $focus_input = strtolower($_POST['focus_select']);
        // $focus_input = preg_replace('/([^a-z0-9, -])/', '', $focus_input);

        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
        return $id;

        // VALIDATE INPUT
        if (!current_user_can('edit_posts') || !$focus_input == 'high' || !$focus_input == 'normal' || !$focus_input == 'skim'){
            return;
        }

        if (!isset($id))
        $id = (int) $_REQUEST['post_ID'];

        if (isset($focus_input)) {
error_log("setting the value");
            update_post_meta($id, 'candela_focus_guid', $focus_input); //($post_id, $meta_key, $meta_value, $prev_value)
        } else {
error_log("clearing the value");
            delete_post_meta($id, 'candela_focus_guid');
        }
    }
}

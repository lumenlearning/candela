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

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

// new FocusRating();
FocusRating::init();

class FocusRating{

    /***********  ACTION: INSTANTIATE, THEN TRIGGER METHODS *****/
    public static function init() {
        if (!defined('CANDELA_FOCUS_META')) {
        define('CANDELA_FOCUS_META', 'candela_focus_meta');
        }
        // if (is_admin()) {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_focus_meta' ) );
        add_action('load-post.php', array( __CLASS__, 'init') );
        add_action('load-post-new.php', array( __CLASS__, 'init') );
        add_filter( 'pb_import_metakeys', array( __CLASS__, 'get_import_metakeys') );
        // }
    }

  /***********  ADD METABOX  *****/
	public static function add_meta_box( $post_type ) {
        $post_types = array('back-matter', 'chapter', 'front-matter');
        if ( in_array( $post_type, $post_types )) {
            add_meta_box('focus', //(ID, title, callback, screen, context, priority, cb args)
            __('Focus Level', 'textdomain'),
            array(__CLASS__, 'focus_metabox_render'),
            $post_type,
            'normal',
            'high'
            );
        }
	}

    /***********  RENDER METABOX  *****/
    public static function focus_metabox_render($post) {
        $set_focus_rating = get_post_meta($post->ID, CANDELA_FOCUS_META, true);
        ?>
        <div class="inside">
            <label for="focus_select"><?php _e( "Set the level of focus appropriate for this content.", 'textdomain' ); ?></label>
            <select id="focus_select" class="select" name="focus_select" selected='<?php $set_focus_rating ?>'>
                <option value="">N/A</option>
                <option value="review" <?php if($set_focus_rating == 'review'){ echo 'selected="selected"'; } ?>>Review</option>
                <option value="normal" <?php if($set_focus_rating == 'normal'){ echo 'selected="selected"'; } ?>>Normal</option>
                <option value="focus" <?php if($set_focus_rating == 'focus'){ echo 'selected="selected"'; } ?>>Focus</option>
            </select>
        </div>
        <?php
    }

    /**
     * Add Focus to to-import meta
     */
    public static function get_import_metakeys( $fields ) {
        $fields[] = 'candela_focus_meta';
        return $fields;
    }

    /*********** SAVE *****/
    public static function save_focus_meta($post_id) {
        $focus_select = isset($_POST['focus_select']);
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
        return $post_id;

        // VALIDATE USER
        if ( 'page' == isset($_POST['post_type']) ) {
            if ( ! current_user_can( 'edit_page', $post_id ) )
                return $post_id;
        } else {
            if ( ! current_user_can( 'edit_post', $post_id ) )
                return $post_id;
        }

        // VALIDATE INPUT
        $focus_select = sanitize_text_field($focus_select);
        if ($focus_select != 'focus' && $focus_select != 'normal' && $focus_select != 'review' && $focus_select != ''){
            return;
        }

        if (!isset($post_id))
        $post_id = (int) $_REQUEST['post_ID'];

        if (isset($focus_select)) {
            update_post_meta($post_id, CANDELA_FOCUS_META, $focus_select); //($id, $meta_key, $meta_value...)
        } else {
            delete_post_meta($post_id, CANDELA_FOCUS_META);
        }
    }
}

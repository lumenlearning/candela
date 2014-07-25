<?php
/*
Plugin Name: WP Admin No Show
Plugin URI: http://www.dougsparling.org
Description: Efectively blocks admin portion of site for selected user roles. Any attempt to manually navigate to wp-admin section of site and user will be redirected to selected site page. Hides admin bar.
Version: 1.4.3
Author: Doug Sparling
Author URI: http://www.dougsparling.org
License: MIT License - http://www.opensource.org/licenses/mit-license.php

Copyright (c) 2012-2013 Doug Sparling
Based on WP Hide Dashboard plugin by Kim Parsell and Admin Bar Disabler plugin by Scott Kingsley Clark

Permission is hereby granted, free of charge, to any person obtaining a copy of this
software and associated documentation files (the "Software"), to deal in the Software
without restriction, including without limitation the rights to use, copy, modify, merge,
publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons
to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or
substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

/**
 * Init on activation
 */
function wp_admin_no_show_activate() {
    // Initialize on first activation
    if ( '' == get_option( 'wp_admin_no_show_redirect_type' ) ) {
        update_option( 'wp_admin_no_show_redirect_type', 'none' );
    }
}
register_activation_hook( __FILE__, 'wp_admin_no_show_activate' );

/**
 * Redirect users on any wp-admin pages
 */
function wp_admin_no_show_admin_redirect() {
    // Whitelist multisite super admin
    if(function_exists('is_multisite')) {
        if( is_multisite() && is_super_admin() ) {
            return;
        }
    }

    if ( 'none' == get_option( 'wp_admin_no_show_redirect_type' ) ) {
        return;
    }

    global $wp_admin_no_show_wp_user_role;
    $disable = false;

    $blacklist_roles = get_option( 'wp_admin_no_show_blacklist_roles', array() );
    if ( false === $disable && !empty( $blacklist_roles ) ) {
        if ( !is_array( $blacklist_roles ) ) {
            $blacklist_roles = array( $blacklist_roles );
        }
        foreach ( $blacklist_roles as $role ) {
            if (preg_match("/administrator/i", $role )) {
                // whitelist administrator for redirect
                continue;
            } else if ( wp_admin_no_show_check_user_role( $role ) ) {
                $disable = true;
            }
        }
    }

    if ( false !== $disable ) {
        if ( 'page' == get_option( 'wp_admin_no_show_redirect_type' ) ) {
            $page_id = get_option( 'wp_admin_no_show_redirect_page' );
            $redirect = get_permalink( $page_id );
        } else {
            $redirect = get_bloginfo( 'url' );
        }

        if( is_admin() ) {
            if ( headers_sent() ) {
                echo '<meta http-equiv="refresh" content="0;url=' . $redirect . '">';
                echo '<script type="text/javascript">document.location.href="' . $redirect . '"</script>';
            } else {
                wp_redirect($redirect);
                exit();
            }
        }

    }
}
add_action( 'admin_head', 'wp_admin_no_show_admin_redirect', 0 );

/**
 * Disable admin bar for users with selected role
 */
function wp_admin_no_show_admin_bar_disable() {
    global $wp_admin_no_show_wp_user_role;
    $disable = false;

    // Whitelist multisite super admin
    if(function_exists('is_multisite')) {
        if( is_multisite() && is_super_admin() ) {
            return;
        }
    }

    $blacklist_roles = get_option( 'wp_admin_no_show_blacklist_roles', array() );
    if ( false === $disable && !empty( $blacklist_roles ) ) {
        if ( !is_array( $blacklist_roles ) ) {
            $blacklist_roles = array( $blacklist_roles );
        }
        foreach ( $blacklist_roles as $role ) {
            if ( wp_admin_no_show_check_user_role( $role ) ) {
                $disable = true;
            }
        }
    }

    if ( false !== $disable ) {
        add_filter( 'show_admin_bar', '__return_false' );
        remove_action( 'personal_options', '_admin_bar_preferences' );
        remove_action( 'wp_head', '_admin_bar_bump_cb' );
    }
}
add_action( 'init', 'wp_admin_no_show_admin_bar_disable' );

/**
 * Checks if a particular user has a role.
 * Returns true if a match was found.
 * http://docs.appthemes.com/tutorials/wordpress-check-user-role-function/
 *
 * @param string $role Role name.
 * @param int $user_id (Optional) The ID of a user. Defaults to the current user.
 * @return bool
 */
function wp_admin_no_show_check_user_role( $role, $user_id = null ) {
    if ( is_numeric( $user_id ) )
        $user = get_userdata( $user_id );
    else
        $user = wp_get_current_user();

    if ( empty( $user ) )
        return false;

    return in_array( $role, (array) $user->roles );
}

/**
 * Create admin menu
 */
function wp_admin_no_show_create_menu() {
    add_options_page( __( 'WP Admin No Show', 'wp-admin-no-show' ), __( 'WP Admin No Show', 'wp-admin-no-show' ), 'administrator', __FILE__, 'wp_admin_no_show_settings_page' );
    add_action( 'admin_init', 'wp_admin_no_show_register_settings' );
}
add_action( 'admin_menu', 'wp_admin_no_show_create_menu' );

function wp_admin_no_show_register_settings() {
    register_setting( 'wp-admin-no-show-settings-group', 'wp_admin_no_show_blacklist_roles' );
    register_setting( 'wp-admin-no-show-settings-group', 'wp_admin_no_show_redirect_type' );
    register_setting( 'wp-admin-no-show-settings-group', 'wp_admin_no_show_redirect_page' );
}

/**
 * Admin settings page
 */
function wp_admin_no_show_settings_page() {
    global $wp_roles;
    if ( !isset( $wp_roles ) ) {
        $wp_roles = new WP_Roles();
    }
    $roles = $wp_roles->get_names();
?>

<div class="wrap">
    <h2><?php _e( 'WP Admin No Show', 'wp-admin-no-show' ); ?></h2>
    <form method="post" action="options.php">
        <?php settings_fields( 'wp-admin-no-show-settings-group' ); ?>
        <?php do_settings_sections( 'wp-admin-no-show-settings-group' ); ?>
        <table class="form-table">

            <tr>
                <td>
                    <h3>User roles you want to blacklist</h3>
                    <?php
                    $blacklist_roles = get_option( 'wp_admin_no_show_blacklist_roles', array() );
                    if ( !is_array( $blacklist_roles ) )
                        $blacklist_roles = array( $blacklist_roles );
                    foreach ( $roles as $role => $name ) {
                        if (preg_match("/administrator/i", $role )) {
                            continue;
                        }
                    ?>
<input name="wp_admin_no_show_blacklist_roles[]" type="checkbox" id="<?php echo 'wp_admin_now_show_role_' . $role; ?>" value="<?php echo $role; ?>" <?php checked('1', in_array( $role, $blacklist_roles )); ?> />
<label for="<?php echo 'wp_admin_now_show_role_' . $role; ?>"><?php _e($name); ?></label>
<br />
                    <?php
                        }
                    ?>

                </td>
            </tr>

            <?php if ( ! get_pages() ) : ?>
            <tr>
            <td><input name="wp_admin_no_show_redirect_type" type="hidden" value="front" /></td>
            </tr>
            <?php
                // If no pages, then default to 'front' if not already set
                if ( 'front' != get_option( 'wp_admin_no_show_redirect_type' ) ) :
                    update_option( 'wp_admin_no_show_redirect_type', 'front' );
                endif;

            else :
                // If pages and no redirect page set, then default to front
                if ( 'page' == get_option( 'wp_admin_no_show_redirect_type' ) && ! get_option( 'wp_admin_no_show_redirect_page' ) )
                    update_option( 'wp_admin_no_show_redirect_type', 'front' );
            ?>

            <tr>
                <td id="front-static-pages">
                    <h3>Where to redirect blacklisted users</h3>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e( 'WP Admin No Show Redirect' ); ?></span></legend>
                        <p>
                            <label>
                                <input name="wp_admin_no_show_redirect_type" type="radio" value="none" class="tog" <?php checked( 'none', get_option( 'wp_admin_no_show_redirect_type' ) ); ?> />
                                <?php _e( 'No redirect (Only hide WP Admin Bar, user will still see admin pages)' ); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input name="wp_admin_no_show_redirect_type" type="radio" value="front" class="tog" <?php checked( 'front', get_option( 'wp_admin_no_show_redirect_type' ) ); ?> />
                                <?php _e( 'Front page' ); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input name="wp_admin_no_show_redirect_type" type="radio" value="page" class="tog" <?php checked( 'page', get_option( 'wp_admin_no_show_redirect_type' ) ); ?> />
                                <?php printf( __( 'A <a href="%s">static page</a> (select below)' ), 'edit.php?post_type=page' ); ?>
                            </label>
                        </p>
                        <ul>
                            <li><label for="wp_admin_no_show_redirect_page"><?php printf( __( 'Redirect page: %s' ), wp_dropdown_pages( array( 'name' => 'wp_admin_no_show_redirect_page', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => get_option( 'wp_admin_no_show_redirect_page' ) ) ) ); ?></label></li>
                        </ul>
                        <em><?php _e( 'Redirect only applies to non-administrator blacklisted roles.<br />', 'wp-admin-no-show' ); ?></em>
                        <em><?php _e( 'Multisite super admin is whitelisted.', 'wp-admin-no-show' ); ?></em>
                    </fieldset>
                </td>
            </tr>
            <?php endif; ?>

        </table>
        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'wp-admin-no-show' ) ?>"/>&nbsp;&nbsp;
        </p>
    </form>
</div>
<?php
}

?>

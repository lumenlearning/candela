<?php
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit ();

delete_option( 'wp_admin_no_show_redirect_type' );
delete_option( 'wp_admin_no_show_redirect_page' );
delete_option( 'wp_admin_no_show_blacklist_roles' );
?>

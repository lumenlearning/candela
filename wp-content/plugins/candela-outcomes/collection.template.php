<?php
/*
 * Template Name: Collection
 */
get_header();
print '<h1>' . __('Learning Outcome Collection') . '</h1>';
Candela\Outcomes\get_collection();
get_sidebar( 'content' );
get_sidebar();
get_footer();

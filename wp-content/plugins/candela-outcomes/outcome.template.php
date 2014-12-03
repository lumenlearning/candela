<?php
/*
 * Template Name: Outcome
 */
get_header();
print '<h1>' . __('Learning Outcome') . '</h1>';
Candela\Outcomes\get_outcome();
get_sidebar( 'content' );
get_sidebar();
get_footer();

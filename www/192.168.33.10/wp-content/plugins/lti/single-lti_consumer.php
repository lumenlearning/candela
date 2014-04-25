<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>
<?php get_header(); ?>
  LTI Metadata:
  <?php if (get_post_meta( $post->ID, LTI_META_KEY_NAME, true):?>
    <h1>Key: <?php echo get_post_meta( $post->ID, LTI_META_KEY_NAME, true); ?></h1>
  <?php endif;?>

  <?php if (get_post_meta( $post->ID, LTI_META_SECRET_NAME, true):?>
      <h1>Secret: <?php echo get_post_meta( $post->ID, LTI_META_SECRET_NAME, true); ?></h1>
  <?php endif;?>
<?php get_footer(); ?>
<?php endwhile;?>

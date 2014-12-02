<?php if ( $outcome->userCanEdit() ) {?>
<span class="edit-link">
  <a class="post-edit-link" href="<?php print esc_attr($outcome->uri(TRUE)); ?>"><?php _e('Edit'); ?></a>
</span>
<?php }?>

<h1><?php print esc_html($outcome->title); ?></h1>

<h2><?php _e('Status'); ?></h2>
<p><?php print $outcome->status; ?></p>

<?php if ( ! empty($outcome->collection ) ) { ?>
  <h2><?php _e('Belongs to'); ?></h2>
  <p><a href="<?php print esc_attr($outcome->collection->uri()); ?>"><?php print esc_html($outcome->collection->title);?></a></p>
<?php } ?>

<h2><?php _e('Description'); ?></h2>
<p><?php print esc_html($outcome->description); ?></p>


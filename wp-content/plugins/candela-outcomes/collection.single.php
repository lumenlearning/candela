<?php if ( $collection->userCanEdit() ) {?>
<span class="edit-link">
  <a class="post-edit-link" href="<?php print esc_attr($collection->uri(TRUE)); ?>"><?php _e('Edit'); ?></a>
</span>
<?php }?>

<h1><?php print esc_html($collection->title); ?></h1>

<h2><?php _e('Status'); ?></h2>
<p><?php print $collection->status; ?></p>

<h2><?php _e('Description'); ?></h2>
<p><?php print esc_html($collection->description); ?></p>


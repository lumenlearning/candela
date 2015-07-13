<style media="screen">

/************/
/*  RESETS  */
/************/

  h1, h2, h3 {
    font-size: 1.4em;
    text-transform: capitalize;
    color: #000;
  }
  p {
    margin: 0;
  }
  img {
    background: transparent;
  }
  a {
    color: #000;
    text-decoration: none;
  }
  #sidebar {
    display: none;
  }
  #wrap {
    width: 100%;
    margin: 0;
  }
  #content {
    box-shadow: none;
    max-width: 100%;
    width: 100%;
    padding: 0;
    margin: 0;
  }


</style>

<!-- MAIN CONTENT -->
<main id="main-content">
	<div id="post-<?php the_ID(); ?>" <?php post_class( pb_get_section_type($post) ); ?>>
		<div class="entry-content">
			<?php
			  the_content();
			  echo get_post_meta( $post->ID, 'pb_part_content', true );
	    ?>
		</div>
	</div>

</div><!-- END CONTENT -->

<?php comments_template( '', true ); ?>

<style media="screen">

/************/
/*  RESETS  */
/************/

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


  /*************/
  /*  GLOBALS  */
  /*************/

  h1, h2, h3 {
    font-size: 1.2em;
    text-transform: capitalize;
    color: #000;
    line-height: 0.5em;
  }

  p {
    font-size: 0.9em;
    color: #000;
    margin: 0;
  }

  img {
    background: transparent;
  }


  /***************************/
  /*  SUB NAV HEADER/FOOTER  */
  /***************************/

  .row {
    background-color: #fff;
  }

  .columns {
    padding: 0;
  }

  .title-row {
    position: relative;
    height: 69px;
    width: 100%;
    background-color: #fff;
  }

  .title-row-content,
  .subheader-content {
    position: absolute;
    top: 50%;
    margin: 0 100px;
  }

  .title-row-content {
    transform: translateY(-45%);
  }

  .subheader-content {
    transform: translateY(-50%);
  }

  .subheader {
    margin: 0;
    height: 160px;
  }

  .content-image {
    width: 175px;
    height: 100px;
    float: left;
  }

  .content-image img {
    height: 100px;
    margin: 0 42px;
    padding: 0;
  }

  .content-description {
    margin-top: 20px;
    width: 750px;
  }

  #why-it-matters,
  #putting-it-together {
    background-color: #007A7C;
  }

  #what-you-know {
    background-color: #eee;
  }

  #why-it-matters h2, #why-it-matters p,
  #putting-it-together h2, #putting-it-together p,
  #quiz h2, #quiz p {
    color: #fff;
  }

  #quiz {
    background-color: #077fab;
  }


  /**************************/
  /*  MAIN CHAPTER CONTENT  */
  /**************************/


  .chapters {
    /*position: relative;*/
    left: 100px;
    width: 100%;
    margin: 8px auto 8px 0;
    box-shadow: 0 1px 10px #a5a5a5;
    border-radius: 5px;
    background-color: #fff;
    align: left;
  }

  .chapters-container {
    width: 800px;
  }

  .chapter-content {
    margin: 30px 20px 20px 0; /* QUESTIONABLE... */
  }

  .chapter-content h3 {
    margin-bottom: 16px;
  }

  .chapter-links {
    border: none;
    border-collapse: separate;
    padding: 0;
    margin: 5px 0 0 0;
    max-width: 600px;
    width: 600px;
    outline: 0;
  }

  .chapter-links td {
    font-size: 0.7em;
    padding: 0;
  }

  .chapter-links .left-column {
    width: 435px;
  }

  .chapter-links a {
    text-decoration: underline;
    color: #3498db;
  }

  .chapter-content h5 {
    line-height: 1.5em;
  }

  .license-attribution {
    background: #fafafa;
  }

</style>

<!-- MAIN CONTENT -->
<main id="main-content">
	<?php // the_title('<h1 class="entry-title">', '</h1>'); ?>

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

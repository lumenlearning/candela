				<div id="secondaryContent">

					
						<?php if (is_active_sidebar( 'sidebar_1')): ?>
						<div class="secondary-box">
				 	    	<?php dynamic_sidebar( 'sidebar_1' ); ?>
				 	    </div>	
				 	      <?php endif; ?>
					
					
					
						<?php if (is_active_sidebar( 'sidebar_2')): ?>
						<div class="secondary-box">
				 	    	<?php dynamic_sidebar( 'sidebar_2' ); ?>
				 	    </div>
				 	    <?php else: ?>
						<div class="secondary-box">
							<h3 class="widget-title">Connect with Us</h3>
							<?php get_template_part( 'widget', 'social-media' ); ?> 
				 	    </div>	
				 	    <?php endif; ?>
					
				</div><!-- end #secondaryContent -->

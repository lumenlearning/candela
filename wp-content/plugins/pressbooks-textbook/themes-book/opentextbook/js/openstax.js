jQuery(function() {
	jQuery("div.cnx-eoc div.exercise").each(function(i,el) {
	  jQuery(el).find("div.solution div.body").hide();
	  jQuery(el).find("div.solution div.title").replaceWith('<a href="#" class="click-toggle-solution">Show Solution</a>');
	  jQuery(el).find("a.solution-number").contents().unwrap();
	});
	jQuery("a.click-toggle-solution").on('click', function(e) {
	     if (jQuery(this).text()=="Show Solution") {
		jQuery(this).text("Hide Solution"); jQuery(this).next().show();
	     } else {
		jQuery(this).text("Show Solution"); jQuery(this).next().hide();
	     }
	     e.preventDefault();
	});
});

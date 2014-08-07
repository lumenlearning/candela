jQuery(function() {
	jQuery(".im_glossterm").each(function(i,el) {
		if (jQuery(el).next().hasClass("im_glossdef")) {
			jQuery(el).addClass("hoverdef")
			jQuery(el).attr("title",jQuery(el).next().text());
		}
	});
});

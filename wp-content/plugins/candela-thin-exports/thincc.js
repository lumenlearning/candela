jQuery(document).ready(function($) {
	
	function thincc_prepare() {
			var $form = $( "#thincc-form" );
			
			$("#thincc-results").html('<strong>Loading...</strong>');

			var data = {
				action: 'thincc_ajax',
				download: 0
			};
			
			return data;
	}
		
	$("a.button-secondary").click(function(event){
		event.preventDefault();
		$("#thincc_modal").show();

		var data = thincc_prepare();
		
		$.post(ajaxurl, data, function(data){
			$("#thincc-results").html(data);
		});

	});
	
	$("#thincc-results-close").click(function(event){
		event.preventDefault();
		$("#thincc_modal").hide();
	});

    // close popup with esc key
    $(document).keyup(function(ev){
        if(ev.keyCode == 27)
            $("#thincc-results-close").click();
    });

});
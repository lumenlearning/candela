jQuery(document).ready(function($) {
	
	function thincc_prepare() {
			var $form = $( "#thincc-form" );
			
			$("#thincc-results").html('<strong>Loading...</strong>');

			var data = {
				action: 'thincc_ajax',
                include_fm: $form.find( 'input[name="include_fm"]:checked' ).val(),
                include_bm: $form.find( 'input[name="include_bm"]:checked' ).val(),
                export_flagged_only: $form.find( 'input[name="export_flagged_only"]:checked' ).val(),
				use_custom_vars: $form.find( 'input[name="use_custom_vars"]:checked' ).val(),
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
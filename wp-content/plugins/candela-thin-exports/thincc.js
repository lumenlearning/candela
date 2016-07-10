jQuery(document).ready(function ($) {

    function thincc_prepare() {
        var $form = $("#thincc-form");

        $("#thincc-results").html('<strong>Loading...</strong>');

        var data = {
            action: 'thincc_ajax',
            export_flagged_only: $form.find('input[name="export_flagged_only"]:checked').val(),
            use_custom_vars: $form.find('input[name="use_custom_vars"]:checked').val(),
            use_web_links: $form.find('input[name="use_web_links"]:checked').val(),
            include_fm: $form.find('input[name="include_fm"]:checked').val(),
            include_bm: $form.find('input[name="include_bm"]:checked').val(),
            include_parts: $form.find('input[name="include_parts"]:checked').val(),
            include_topics: $form.find('input[name="include_topics"]:checked').val(),
            include_assignments: $form.find('input[name="include_assignments"]:checked').val(),
            include_guids: $form.find('input[name="include_guids"]:checked').val(),
            version: $form.find('select[name="version"] option:selected').val(),
            cc_download: 0
        };

        return data;
    }

    $("a.button-secondary").click(function (event) {
        event.preventDefault();
        $("#thincc_modal").show();

        var data = thincc_prepare();

        $.post(ajaxurl, data, function (data) {
            $("#thincc-results").html(data);
        });

    });

    $("#thincc-results-close").click(function (event) {
        event.preventDefault();
        $("#thincc_modal").hide();
    });

    // close popup with esc key
    $(document).keyup(function (ev) {
        if (ev.keyCode == 27)
            $("#thincc-results-close").click();
    });

});

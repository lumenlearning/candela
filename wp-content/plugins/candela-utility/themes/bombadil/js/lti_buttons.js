/**
 * LTI Navigation button functionality within LMS
 */

if (self == top){
    jQuery(".lti-bottom-nav-buttons").hide();
} else {
    jQuery("#lti-prev").click(function () {
        parent.postMessage(JSON.stringify({
            subject: "lti.navigation",
            location: "previous"
        }), "*");
    });

    jQuery("#study-plan").click(function () {
        parent.postMessage(JSON.stringify({
            subject: "lti.navigation",
            location: "home"
        }), "*");
    });

    jQuery("#lti-next").click(function () {
        parent.postMessage(JSON.stringify({
            subject: "lti.navigation",
            location: "next"
        }), "*");
    });
}

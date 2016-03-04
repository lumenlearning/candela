/**
 * Listen for a window post message to resize an embedded iframe
 * Needs to be an json stringified object that identifies the id of
 * the element to resize like this:

   parent.postMessage(JSON.stringify({
      subject: "lti.frameResize",
      height: default_height,
      element_id: "lumen_assessment_1"
  }), "*");

 * The element_id needed is passed as a query parameter `iframe_resize_id`
 */
if (self == top) {
    console.log(window.location.href + "setting up listener");
    window.addEventListener('message', function (e) {
        console.log(window.location.href + "got message");
        console.log(e.data);
        try {
            var message = JSON.parse(e.data);
            switch (message.subject) {
                case 'lti.frameResize':
                    var $iframe = jQuery('#' + message.element_id);
                    if ($iframe.length == 1 && $iframe.hasClass('resizable')) {
                        var height = message.height;
                        if (height >= 5000) height = 5000;
                        if (height <= 0) height = 1;

                        $iframe.css('height', height + 'px');
                    }
                    break;
            }
        } catch (err) {
            (console.error || console.log)('invalid message received from ', e.origin);
        }
    });
}

/**
  * Prevents browser from opening a new tab to play embedded audio.
*/

jQuery(".translation a[href$=mp3]").each(function() {
  var audioURL = jQuery(this).attr('href');
  jQuery(this).attr("href",""); var audio = new Audio(audioURL);
  jQuery(this).on('click', function(e) { e.preventDefault(); audio.play();});
});

(function ($, Drupal, drupalSettings) {

  var getWindowOptions = function() {
    var width = 500;
    var height = 350;
    var left = (window.innerWidth / 2) - (width / 2);
    var top = (window.innerHeight / 2) - (height / 2);

    return [
      'resizable,scrollbars,status',
      'height=' + height,
      'width=' + width,
      'left=' + left,
      'top=' + top,
    ].join();
  };


  Drupal.behaviors.nativeShareLinkTwitterBehavior = {
    attach: function (context, settings) {

      $('.share_modal', context).once('share-modal-twitter').each(function () {

        var app_id = drupalSettings.facebook.facebookJS.app_id;

        $.ajaxSetup({ cache: true });
        $.getScript('https://platform.twitter.com/widgets.js', function(){
        });


        $('a.twitter-share').on('click', function(e) {
          e.preventDefault();

          var socials = sessionStorage.getItem('socials') ? JSON.parse(sessionStorage.getItem('socials')) : [];

          var shareUrl = 'https://twitter.com/intent/tweet?url=' + socials.page; // + '&text=' + meta-description;

          var win = window.open(shareUrl, 'ShareOnTwitter', getWindowOptions());
          win.opener = null;

        });

      });

    }
  };
})(jQuery, Drupal, drupalSettings);
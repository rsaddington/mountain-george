(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.nativeShareLinkFacebookBehavior = {
    attach: function (context, settings) {

      $('.share_modal', context).once('share-modal-fb').each(function () {

      var app_id = drupalSettings.facebook.facebookJS.app_id;

      $.ajaxSetup({ cache: true });
      $.getScript('https://connect.facebook.net/en_US/sdk.js', function(){
        FB.init({
          appId: app_id,
          autoLogAppEvents : true,
          xfbml            : true,
          version: 'v4.0'
        });
      });


      $('a.facebook-share').on('click', function(e) {
        e.preventDefault();

        var socials = sessionStorage.getItem('socials') ? JSON.parse(sessionStorage.getItem('socials')) : [];

        FB.ui({
            method: 'share',
            href: socials.page
           }, function(response){});
        });

      });

    }
  };
})(jQuery, Drupal, drupalSettings);
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.nativeShareLinkPinterestBehavior = {
    attach: function (context, settings) {

      $('.share_modal', context).once('share-modal-pin').each(function () {

        $.ajaxSetup({ cache: true });
        $.getScript('//assets.pinterest.com/js/pinit.js', function(){
        });


        $('a.pinterest-share').on('click', function(e) {
          e.preventDefault();

          //Share any image on the page
          PinUtils.pinAny();

        });

      });

    }
  };
})(jQuery, Drupal, drupalSettings);
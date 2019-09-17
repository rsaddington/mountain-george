(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.nativeShareLinkBehavior = {
    attach: function (context, settings) {

      /**
       * Copy URL
       */
      var copy = function () {
        var copyUrl = document.querySelector('input.url');
        copyUrl.select();
        document.execCommand('copy');
      }

      $('[data-share-this]', context).once('[data-share-this]').each(function (index, data) {

        $(this).on('click', function (e) {
          e.preventDefault();

          sessionStorage.setItem('socials', JSON.stringify({page: $(this).attr('href')}));

          $(".share_modal__link input.url").val($(this).attr('href'));

          //Set mailto value
          var mailto = 'mailto:?&subject=' + drupalSettings.nsl.sitename + '&body=' + encodeURIComponent($(this).attr('href'));

          $("a.email-share").attr('href', mailto);

          $(".share_modal").show();

        });

        $('button.copy-url').on('click', copy);
      });

    }
  };
})(jQuery, Drupal, drupalSettings);


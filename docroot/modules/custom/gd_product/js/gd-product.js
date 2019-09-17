/**
 * @file
 * GD Product behaviors.
 */
(function ($, Drupal) {

  'use strict';

  if (typeof Drupal.GD === 'undefined') {
    Drupal.GD = {};
  }

  /**
   * Initialises product listing block.
   */
  Drupal.behaviors.gdProductInitProductListing = {
    attach: function (context, settings) {
      if (!Drupal.GD.products.listingBlockInit) {
        // Initialising products listing.
        Drupal.GD.products.initsProductListing();
      }
    }
  };

  Drupal.behaviors.gdProductRefreshFilters = {
    attach: function (context, settings) {
      Drupal.GD.products.refreshProductListing(context);
    }
  }

  /**
   * Initialises product listing block.
   */
  Drupal.behaviors.gdProductInitProductDetails = {
    attach: function (context, settings) {
      if (!Drupal.GD.products.detailsBlockInit) {
        // Initialising products listing.
        Drupal.GD.products.initsProductDetails();
      }
    }
  };


  Drupal.GD.products = {
    listingBlockInit: false,
    detailsBlockInit: false,
    updateInProgress: true,
    listing: {
      filters:[],
      token: '',
    },
    initsProductListing: function() {
      Drupal.GD.products.listingBlockInit = true;
      let productListingInstance = $('#product-listing');
      let token = productListingInstance.data('token');
      Drupal.GD.products.listing.token = token;
      if (productListingInstance.length === 0 || productListingInstance.length > 1) {
        return;
      }

      let settings = {
        url: Drupal.url('product-listing/init?token=' + token),
      };

      let initRequest = Drupal.ajax(settings);
      initRequest.execute();
    },
    initsProductDetails: function() {
      Drupal.GD.products.detailsBlockInit = true;

      let productDetailsInstance = $('#product-details');
      if (productDetailsInstance.length === 0 || productDetailsInstance.length > 1) {
        return;
      }

      if (!(/^node\/\d+$/).test(drupalSettings.path.currentPath)) {
        return;
      }


      let nodeId = drupalSettings.path.currentPath.match(/\d+/)[0];
      let settings = {
        url: '/' + drupalSettings.path.pathPrefix + 'product/' + nodeId + '/init'
      };

      let initRequest = Drupal.ajax(settings);
      initRequest.execute();
    },
    preventActions: function() {
      if (Drupal.GD.products.updateInProgress == true) {
        return;
      }
      Drupal.GD.products.updateInProgress = true;
      $('.product-listing-filter').prop( 'disabled', true );
      $('.product-listing-filters').addClass('loading');

      $(document).trigger('gdProductUpdateInProgress');
    },
    refreshProductListing: function(context) {
      // Drupal.GD.products = false;
      let $filters = $('.product-listing-filter:checked');

      $filters.each(function(index, filter) {
        let filterId = $(filter).data('filter-id');
        let filterValue = $(filter).data('filter-value');

        if (typeof Drupal.GD.products.listing.filters[filterId] === "undefined") {
          Drupal.GD.products.listing.filters[filterId] = {};
        }
        Drupal.GD.products.listing.filters[filterId][filterValue] = true;
      });


      $(context).find('.product-listing-load-more').on('click', function(event) {
        event.preventDefault();

        Drupal.GD.products.preventActions();
        let offset = $(this).data('offset');

        let query = '';
        for (let filterIdProp in Drupal.GD.products.listing.filters) {
          for (let filterValueProp in Drupal.GD.products.listing.filters[filterIdProp]) {
            query += '&filters[' + filterIdProp + '][]=' + filterValueProp;
          }
        }

        query += '&offset=' + offset;

        let settings = {
          url: Drupal.url('product-listing/load-more?token=' + Drupal.GD.products.listing.token + query),
        };
        let initRequest = Drupal.ajax(settings);
        initRequest.execute();
      });

      $('.product-listing-filter', context).on('change', function() {

        Drupal.GD.products.preventActions();
        let filterId = $(this).data('filter-id');
        let filterValue = $(this).data('filter-value');

        if ($(this).prop('checked')) {
          if (typeof Drupal.GD.products.listing.filters[filterId] === "undefined") {
            Drupal.GD.products.listing.filters[filterId] = {};
          }
          Drupal.GD.products.listing.filters[filterId][filterValue] = true;
        }
        else {
          delete Drupal.GD.products.listing.filters[filterId][filterValue];
        }

        let query = '';
        for (let filterIdProp in Drupal.GD.products.listing.filters) {
          for (let filterValueProp in Drupal.GD.products.listing.filters[filterIdProp]) {
            query += '&filters[' + filterIdProp + '][]=' + filterValueProp;
          }
        }


        let settings = {
          url: Drupal.url('product-listing/filter?token=' + Drupal.GD.products.listing.token + query),
        };
        let initRequest = Drupal.ajax(settings);
        initRequest.execute();
      });
    }
  };

  Drupal.AjaxCommands.prototype.productDetailsInit = function (ajax, response, status) {
    let method = 'html';
    let settings = response.settings || ajax.settings || drupalSettings;


    // Replacing details block.
    let $wrapper = $('#product-details-wrapper');
    let $content = $($.parseHTML(response.details, document, true));

    $content = Drupal.theme('ajaxWrapperNewContent', $content, ajax, response);

    $wrapper[method]($content);
    if ($content.parents('html').length) {
      $content.each(function (index, element) {
        if (element.nodeType === Node.ELEMENT_NODE) {
          Drupal.attachBehaviors(element, settings);
        }
      });
    }

    // Replacing specs block.
    $wrapper = $('#product-specs-wrapper');
    $content = $($.parseHTML(response.specs, document, true));
    $content = Drupal.theme('ajaxWrapperNewContent', $content, ajax, response);

    $wrapper[method]($content);
    if ($content.parents('html').length) {
      $content.each(function (index, element) {
        if (element.nodeType === Node.ELEMENT_NODE) {
          Drupal.attachBehaviors(element, settings);
        }
      });
    }

  };

  Drupal.AjaxCommands.prototype.productListingInit = function (ajax, response, status) {
    let $wrapper = $('#product-listing-wrapper');
    let method = 'html';
    let settings = response.settings || ajax.settings || drupalSettings;
    let $newContent = $($.parseHTML(response.content, document, true));

    $newContent = Drupal.theme('ajaxWrapperNewContent', $newContent, ajax, response);
    Drupal.detachBehaviors($wrapper.get(0), settings);

    $wrapper[method]($newContent);

    if ($newContent.parents('html').length) {
      $newContent.each(function (index, element) {
        if (element.nodeType === Node.ELEMENT_NODE) {
          Drupal.attachBehaviors(element, settings);
        }
      });
    }

    $(document).trigger('gdProductListingUpdated');
    Drupal.GD.products.updateInProgress = false;
  };

  Drupal.AjaxCommands.prototype.productListingLoadMore = function (ajax, response, status) {
    let settings = response.settings || ajax.settings || drupalSettings;

    // Appending new products here.
    let $newContent = $($.parseHTML(response.content, document, true));
    $newContent = Drupal.theme('ajaxWrapperNewContent', $newContent, ajax, response);
    $('#product-listing').append($newContent);

    if ($newContent.parents('html').length) {
      $newContent.each(function (index, element) {
        if (element.nodeType === Node.ELEMENT_NODE) {
          Drupal.attachBehaviors(element, settings);
        }
      });
    }



    let $loadMoreWrapper = $('#product-listing-pagination');
    let $loadMore = $($.parseHTML(response.loadMore, document, true));
    let method = 'html';

    $loadMoreWrapper[method]($loadMore);
    Drupal.detachBehaviors($loadMoreWrapper.get(0), settings);

    if ($loadMore.parents('html').length) {
      Drupal.attachBehaviors($loadMoreWrapper.get(0), settings);
    }

    $(document).trigger('gdProductListingUpdated');

    Drupal.GD.products.updateInProgress = false;
  };


} (jQuery, Drupal));


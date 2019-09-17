<?php

namespace Drupal\gd_product\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsTrait;

/**
 * AJAX command for updating message board.
 *
 * @ingroup ajax
 */
class ProductListingLoadMore implements CommandInterface, CommandWithAttachedAssetsInterface {

  use CommandWithAttachedAssetsTrait;

  protected $content;
  protected $loadMoreButton;

  /**
   * UpdateMessageBoard constructor.
   *
   * @param array $content
   *   Render array content.
   * @param $load_more_button
   */
  public function __construct(array $content, $load_more_button) {
    $this->content = $content;
    $this->loadMoreButton = $load_more_button;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'productListingLoadMore',
      'content' => $this->getRenderedContent(),
      'loadMore' => \Drupal::service('renderer')->renderRoot($this->loadMoreButton),
    ];
  }
}

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
class ProductListingInit implements CommandInterface, CommandWithAttachedAssetsInterface {

  use CommandWithAttachedAssetsTrait;

  protected $content;

  /**
   * UpdateMessageBoard constructor.
   *
   * @param array $content
   *   Render array content.
   */
  public function __construct(array $content) {
    $this->content = $content;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'productListingInit',
      'content' => $this->getRenderedContent(),
    ];
  }
}

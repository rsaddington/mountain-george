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
class ProductDetailsInit implements CommandInterface, CommandWithAttachedAssetsInterface {

  use CommandWithAttachedAssetsTrait;

  protected $content;
  protected $specs;

  /**
   * UpdateMessageBoard constructor.
   *
   * @param array $details
   *   Render array content.
   * @param array $specs
   */
  public function __construct(array $details, array $specs) {
    $this->content = $details;
    $this->specs = $specs;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'productDetailsInit',
      'details' => $this->getRenderedContent(),
      'specs' => \Drupal::service('renderer')->renderRoot($this->specs),
    ];
  }
}

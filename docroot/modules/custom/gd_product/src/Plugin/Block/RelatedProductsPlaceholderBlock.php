<?php

namespace Drupal\gd_product\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a related products block.
 *
 * @Block(
 *   id = "gd_product_related_products_placeholder",
 *   admin_label = @Translation("Related Products"),
 *   category = @Translation("Product")
 * )
 */
class RelatedProductsPlaceholderBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}

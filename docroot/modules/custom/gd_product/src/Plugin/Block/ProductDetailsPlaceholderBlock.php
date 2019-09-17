<?php

namespace Drupal\gd_product\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a product details block.
 *
 * @Block(
 *   id = "gd_product_product_details_placeholder",
 *   admin_label = @Translation("Product Details"),
 *   category = @Translation("Product")
 * )
 */
class ProductDetailsPlaceholderBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = [
      '#theme' => 'gd_product_details_placeholder',
    ];

    $build = [
      'content' => $content,
      '#attached' => [
        'library' => [
          'gd_product/gd_product.product',
        ]
      ],
      '#cache' => [
        'max-age' => 0,
//        'contexts' => [
//          'region',
//        ],
      ],
    ];

    return $build;
  }

}

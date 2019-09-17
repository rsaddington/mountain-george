<?php

namespace Drupal\gd_product\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a product specifications block.
 *
 * @Block(
 *   id = "gd_product_product_specifications_placeholder",
 *   admin_label = @Translation("Product Specifications"),
 *   category = @Translation("Product")
 * )
 */
class ProductSpecificationsPlaceholderBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $content = [
      '#theme' => 'gd_product_specs_placeholder',
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

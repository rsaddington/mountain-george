<?php

namespace Drupal\gd_commercetools_integration\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for gd_commercetools_integration routes.
 */
class GdCommercetoolsintegrationController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}

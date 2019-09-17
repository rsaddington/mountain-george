<?php

namespace Drupal\gd_utility\Element;

use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Template\Attribute;

/**
 * Provides a element fot a selectboxes.
 *
 * This is purely FE element, do not use on forms!
 *
 * @RenderElement("selectboxes")
 */
class SelectBoxes extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    return [
      '#pre_render' => [
        [$class, 'preRenderSelectBoxes'],
      ],
      '#theme_wrappers' => ['gd_utility_selectboxes'],
    ];
  }

  /**
   * @param $element
   * @return array
   */
  public static function preRenderSelectBoxes($element) {
    if (count($element['#options']) > 0) {
      foreach ($element['#options'] as $key => &$checkbox) {
        if (isset($checkbox['attributes'])) {
          $checkbox['attributes'] = new Attribute($checkbox['attributes']);
        }
      }
    }

    return $element;
  }

}

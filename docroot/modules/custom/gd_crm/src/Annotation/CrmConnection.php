<?php

namespace Drupal\gd_crm\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a CrmConnection annotation object.
 *
 * @Annotation
 */
class CrmConnection extends Plugin {

  /**
   * @ingroup plugin_translatable
   *
   * @var Translation
   */
  public $label;

  /**
   * @ingroup plugin_translatable
   *
   * @var Translation
   */
  public $description;

}

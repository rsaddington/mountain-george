<?php


namespace Drupal\gd_commercetools_integration;


use Commercetools\Core\Model\Category\Category;
use Commercetools\Core\Model\Product\ProductProjection;

class CategoryCommerceTools implements CategoryInterface {

  /**
   * @var Category
   */
  private $ctCategory;

  /**
   * @var array
   */
  protected $languageMapping;

  /**
   * @param Category $category
   * @param array $language_mapping
   */
  public function __construct(Category $category, array $language_mapping) {
    $this->ctCategory = $category;
    $this->languageMapping = $language_mapping;
  }

  public function getId() {
    return $this->ctCategory->getId();
  }

  public function getName($langcode) {
    return $this->ctCategory->getName()->{$this->languageMapping[$langcode]};
  }

  public function getVersion() {
    return $this->ctCategory->getVersion();
  }

  public function getNameUnlocalised() {
    $names = $this->ctCategory->getName()->toArray();
    $mapping = array_flip($this->languageMapping);

    $drupal_friendly_names = [];
    foreach ($names as $lang => $name) {
      $drupal_friendly_names[$mapping[$lang]] = $name;
    }

    return $drupal_friendly_names;
  }
}
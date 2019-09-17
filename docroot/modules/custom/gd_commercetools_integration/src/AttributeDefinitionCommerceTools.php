<?php


namespace Drupal\gd_commercetools_integration;


use Commercetools\Core\Model\ProductType\AttributeDefinition;

class AttributeDefinitionCommerceTools implements AttributeDefinitionInterface {

  /**
   * @var array
   */
  protected $languageMapping;

  /**
   * @var AttributeDefinition
   */
  private $ctAttribute;

  /**
   * @param AttributeDefinition $product_attribute
   * @param array $language_mapping
   */
  public function __construct(AttributeDefinition $product_attribute, array $language_mapping) {
    $this->languageMapping = $language_mapping;
    $this->ctAttribute = $product_attribute;
  }

  public function getId() {
    return $this->ctAttribute->getName();
  }

  public function getName($langcode) {
    if ($langcode && !isset($this->languageMapping[$langcode])) {
      return '';
    }

    return $this->ctAttribute->getLabel()->{$this->languageMapping[$langcode]} ?? '';
  }

  public function getNameUnlocalised() {
    $names = $this->ctAttribute->getLabel()->toArray();
    $mapping = array_flip($this->languageMapping);

    $drupal_friendly_names = [];
    foreach ($names as $lang => $name) {
      $drupal_friendly_names[$mapping[$lang]] = $name;
    }

    return $drupal_friendly_names;
  }

  public function getType() {
    return $this->ctAttribute->getType()->name;
  }

  public function getValues($langcode) {

    if (!in_array($this->getType(), ['enum', 'lenum'])) {
      return [];
    }

    if ($this->getType() == 'enum') {
      $values = $this->ctAttribute->getType()->toArray()['values'];
      $processed_values = [];
      foreach ($values as $value) {
        $processed_values[$value['key']] = $value['label'];
      }

      return $processed_values;
    }
    else if ($this->getType() == 'lenum') {
      $values = $this->ctAttribute->getType()->toArray()['values'];
      $mapping = array_flip($this->languageMapping);

      $processed_values = [];
      foreach ($values as &$value) {
        $drupal_friendly_values = [];
        foreach ($value['label'] as $lang => $label) {
          $drupal_friendly_values[$mapping[$lang]] = $label;
        }

        $processed_values[$value['key']] = $drupal_friendly_values;
      }

      return $processed_values;
    }
  }

  public function getFacetDefinition($langcode) {
    // TODO: Implement getFacetDefinition() method.
  }

  public function isSearchable() {
    return $this->ctAttribute->getIsSearchable();
  }
}
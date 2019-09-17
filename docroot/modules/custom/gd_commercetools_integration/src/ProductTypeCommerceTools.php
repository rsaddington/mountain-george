<?php


namespace Drupal\gd_commercetools_integration;


use Commercetools\Core\Model\ProductType\ProductType;

class ProductTypeCommerceTools implements ProductTypeInterface {
  /**
   * @var ProductType
   */
  private $ctProductType;

  /**
   * @var array
   */
  protected $languageMapping;

  /**
   * @param ProductType $product_type
   * @param array $language_mapping
   */
  public function __construct(ProductType $product_type, array $language_mapping) {
    $this->languageMapping = $language_mapping;
    $this->ctProductType = $product_type;
  }

  public function getName($langcode) {
    return $this->ctProductType->getName();
  }

  public function getId() {
    return $this->ctProductType->getId();
  }

  public function getAttributeDefinitions() {
    $processed_attributes = [];
    foreach ($this->ctProductType->getAttributes() as $attribute) {
      $processed_attribute = new AttributeDefinitionCommerceTools($attribute, $this->languageMapping);
      $processed_attributes[$processed_attribute->getId()] = $processed_attribute;
    }

    return $processed_attributes;
  }
}
<?php


namespace Drupal\gd_commercetools_integration;


use Commercetools\Core\Model\Common\Enum;
use Commercetools\Core\Model\Common\LocalizedEnum;
use Commercetools\Core\Model\Common\LocalizedString;
use Commercetools\Core\Model\Product\ProductVariant;

class VariantCommerceTools implements VariantInterface {

  /**
   * @var ProductVariant
   */
  protected $ctVariant;

  /**
   * @var array
   */
  protected $languageMapping;

  /**
   * VariantCommerceTools constructor.
   * @param ProductVariant $variant
   * @param $language_mapping
   */
  public function __construct(ProductVariant $variant, $language_mapping) {
    $this->ctVariant = $variant;
    $this->languageMapping = $language_mapping;
  }

  /**
   * {@inheritDoc}
   */
  public function getAttributes($langcode) {
    $processed_attributes = [];
    foreach ($this->ctVariant->getAttributes() as $attribute) {

      $processed_attribute = new AttributeCommerceTools($attribute, $this->languageMapping);
      $processed_attributes[$processed_attribute->getId()] = $processed_attribute;
    }

    return $processed_attributes;
  }

  /**
   * {@inheritDoc}
   */
  public function getImages() {
    $images = $this->ctVariant->getImages();

    $result = [];
    foreach ($images as $image) {
      $result[] = [
        'url' => $image->getUrl(),
        'title' => $image->getLabel(),
        'alt' => $image->getLabel(),
        'thumb_url' => $image->getSmall(),
      ];
    }

    return $result;
  }

  public function getId() {
    return $this->ctVariant->getKey();
  }

  public function getTitle() {
    return $this->ctVariant->getSku();
  }
}

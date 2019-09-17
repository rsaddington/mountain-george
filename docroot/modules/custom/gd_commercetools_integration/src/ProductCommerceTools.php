<?php

namespace Drupal\gd_commercetools_integration;

use Commercetools\Core\Model\Category\Category;
use Commercetools\Core\Model\Category\CategoryReference;
use Commercetools\Core\Model\Product\ProductProjection;

/**
 * Class ProductCommerceTools
 *
 * @todo: complete.
 * @package Drupal\gd_commercetools_integration
 */
class  ProductCommerceTools implements ProductInterface {

  /**
   * @var ProductProjection
   */
  protected $ctProduct;

  /**
   * @var array
   */
  protected $languageMapping;

  /**
   * @param ProductProjection $product
   * @param array $language_mapping
   */
  public function __construct(ProductProjection $product, array $language_mapping) {
    $this->ctProduct = $product;
    $this->languageMapping = $language_mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->ctProduct->getKey();
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle($langcode) {
    return $this->ctProduct->getName()->{$this->languageMapping[$langcode]};
  }

  /**
   * {@inheritdoc}
   */
  public function getVariants() {
    $variants = $this->ctProduct->getVariants();
    $master_variant = $this->ctProduct->getMasterVariant();

    $result = [];
    $result[] = new VariantCommerceTools($master_variant, $this->languageMapping);
    foreach ($variants as $variant) {
      $result[] = new VariantCommerceTools($variant, $this->languageMapping);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getImages() {
    $master_variant = $this->ctProduct->getMasterVariant();
    $images = $master_variant->getImages();

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

  /**
   * {@inheritdoc}
   */
  public function getMetadata($langcode) {
    return [
      'title' => $this->ctProduct->getMetaTitle() ? $this->ctProduct->getMetaTitle()->toArray()[$this->languageMapping[$langcode]] ?? '' : '',
      'description' => $this->ctProduct->getMetaDescription() ? $this->ctProduct->getMetaDescription()->toArray()[$this->languageMapping[$langcode]] ?? '' : '',
      'keywords' => $this->ctProduct->getMetaKeywords() ? $this->ctProduct->getMetaKeywords()->toArray()[$this->languageMapping[$langcode]] ?? '' : '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription($langcode) {
    return $this->ctProduct->getDescription() ? $this->ctProduct->getDescription()->toArray()[$this->languageMapping[$langcode]] ?? '' : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailability() {
    $variants = $this->ctProduct->getVariants();
    $variants[] = $this->ctProduct->getMasterVariant();

    $availability = [];
    foreach ($variants as $variant) {
      $avail = $variant->getAvailability();
      if (!$avail || !$avail->getChannels()) {
        continue;
      }

      foreach ($avail->getChannels() as $channel_id => $channel) {
        $availability[$channel_id][] = new VariantCommerceTools($variant, $this->languageMapping);
      }
    }

    return $availability;
  }

  /**
   * {@inheritdoc}
   */
  public function getCategoryIds() {
    /* @var $categories CategoryReference[] */
    $categories = $this->ctProduct->getCategories();

    $result = [];
    foreach ($categories as $category) {
      $result[] = $category->getId();
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion() {
    return $this->ctProduct->getVersion();
  }

  public function getTypeId() {
    return $this->ctProduct->getProductType()->getId();
  }
}
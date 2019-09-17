<?php


namespace Drupal\gd_commercetools_integration;


use Commercetools\Core\Model\Product\FacetResult;
use Commercetools\Core\Model\ProductType\AttributeDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class FacetCategoryCommerceTools implements FacetInterface {
  use StringTranslationTrait;

  /**
   * @var FacetResult
   */
  private $ctFacet;
  private $id;
  private $categories;

  /**
   * FacetCommerceTools constructor.
   * @param $id
   * @param FacetResult $facet
   * @param CategoryInterface[] $categories
   */
  public function __construct($id, FacetResult $facet, $categories) {
    $this->ctFacet = $facet;
    $this->id = $id;
    $this->categories = $categories;
  }

  public function getItems($langcode) {
    $items = [];

    foreach ($this->ctFacet->getTerms() as $term) {
      $items[$term->getTerm()] = [
        'name' => $this->categories[$term->getTerm()]->getName($langcode),
        'count' => $term->getProductCount(),
      ];
    }

    return $items;
  }

  public function getId() {
    return $this->id;
  }

  public function getTitle($langcode) {
    return $this->t('Categories');
  }
}
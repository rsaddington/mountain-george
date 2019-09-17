<?php


namespace Drupal\gd_commercetools_integration;


use Commercetools\Core\Model\Product\FacetResult;
use Commercetools\Core\Model\ProductType\AttributeDefinition;

class FacetAttributeCommerceTools implements FacetInterface {


  /**
   * @var FacetResult
   */
  private $ctFacet;
  private $id;

  /**
   * @var AttributeDefinitionInterface
   */
  private $ctAttributeDefinition;

  /**
   * FacetCommerceTools constructor.
   * @param $id
   * @param FacetResult $facet
   * @param AttributeDefinitionInterface $attribute_definition
   */
  public function __construct($id, FacetResult $facet, AttributeDefinitionInterface $attribute_definition) {
    $this->ctFacet = $facet;
    $this->id = $id;
    $this->ctAttributeDefinition = $attribute_definition;
  }

  public function getItems($langcode) {
    $items = [];

    foreach ($this->ctFacet->getTerms() as $term) {
      switch ($this->ctAttributeDefinition->getType()) {
        case 'text':
        case 'ltext':
          $items[$term->getTerm()] = [
            'name' => $term->getTerm(),
            'count' => $term->getProductCount(),
          ];
          break;

        case 'enum':
        case 'lenum':
          $items[$term->getTerm()] = [
            'name' => $this->ctAttributeDefinition->getValues($langcode)[$term->getTerm()],
            'count' => $term->getProductCount(),
          ];
          break;

        default:
          continue 2;
      }
    }

    return $items;
  }

  public function getId() {
    return $this->id;
  }

  public function getTitle($langcode) {
    return $this->ctAttributeDefinition->getName($langcode);
  }
}
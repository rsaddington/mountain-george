<?php


namespace Drupal\gd_commercetools_integration;


interface ProductTypeInterface {
  public function getName($langcode);
  public function getId();

  /**
   * @return AttributeDefinitionInterface[]
   */
  public function getAttributeDefinitions();
}
<?php


namespace Drupal\gd_commercetools_integration;


interface AttributeDefinitionInterface {
  public function getId();
  public function getName($langcode);
  public function getNameUnlocalised();
  public function getType();
  public function getValues($langcode);
  public function getFacetDefinition($langcode);
  public function isSearchable();
}
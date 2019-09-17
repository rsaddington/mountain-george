<?php


namespace Drupal\gd_commercetools_integration;


interface AttributeInterface {
  public function getValue($langcode);
  public function getId();
}
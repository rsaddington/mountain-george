<?php

namespace Drupal\gd_commercetools_integration;


interface FacetInterface {
  public function getItems($langcode);
  public function getId();
  public function getTitle($langcode);
}
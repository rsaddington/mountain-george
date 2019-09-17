<?php


namespace Drupal\gd_commercetools_integration;


interface CategoryInterface {
  public function getId();
  public function getName($langcode);
  public function getNameUnlocalised();
  public function getVersion();
}
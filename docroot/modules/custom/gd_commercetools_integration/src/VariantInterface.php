<?php


namespace Drupal\gd_commercetools_integration;


interface VariantInterface
{

  /**
   * @param $langcode
   * @return AttributeInterface[]
   */
  public function getAttributes($langcode);

  /**
   * Return images array, including url, title and alt.
   *
   * @return array
   *
   * <pre>
   * [
   *   'url' => 'http://example.com/image.jpg',
   *   'title' => 'Image Title',
   *   'alt' => 'Image Alt',
   * ];
   * </pre>
   */
  public function getImages();

  public function getId();

  public function getTitle();

}
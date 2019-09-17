<?php


namespace Drupal\gd_commercetools_integration;


interface ProductProviderInterface
{
  /**
   * @param int $limit
   * @param int $offset
   *
   * @param $filters
   * @return array
   */
  public function getProducts($filters, $limit, $offset);

  /**
   * @return ProductInterface[]
   */
  public function getProductsAll();

  /**
   * @param array $ids
   * @return ProductInterface[]
   */
  public function getProductsByIds($ids);

  /**
   * @param $ids
   * @return CategoryInterface[]
   */
  public function getProductCategoriesByIds($ids);

  /**
   * Return languages mapping between Drupal and other platform languages.
   *
   * <code>
   * [
   *   'en' => 'en_US',
   *   'de' => 'de_DE',
   * ];
   * </code>
   *
   * @return array
   */
  public function getLanguageMapping();

  /**
   * @return ProductTypeInterface[]
   */
  public function getProductTypes();

  /**
   * @return CategoryInterface[]
   */
  public function getProductCategories();

  /**
   * @return AttributeDefinitionInterface[]
   */
  public function getAttributeDefinitions();

  /**
   * @param ProductTypeInterface[] $product_types
   * @return AttributeDefinitionInterface[] array
   */
  public function mergeProductTypeAttributeDefinitions($product_types);
}
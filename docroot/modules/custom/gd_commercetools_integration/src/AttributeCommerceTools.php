<?php


namespace Drupal\gd_commercetools_integration;


use Commercetools\Core\Model\Common\Attribute;
use Commercetools\Core\Model\Common\AttributeCollection;
use Commercetools\Core\Model\Common\Enum;
use Commercetools\Core\Model\Common\LocalizedEnum;
use Commercetools\Core\Model\Common\LocalizedString;

class AttributeCommerceTools implements AttributeInterface {

  /**
   * @var Attribute
   */
  private $ctAttribute;

  private $languageMapping;

  public function __construct(Attribute $ct_attribute, $language_mapping) {
    $this->ctAttribute = $ct_attribute;
    $this->languageMapping = $language_mapping;
  }

  public function getValue($langcode) {
    return $this->parseAttribute($this->ctAttribute, $langcode);
  }

  /**
   * @param Attribute $attribute
   * @param $langcode
   * @return array|string
   */
  private function parseAttribute($attribute, $langcode) {
    if ($attribute->getValue() instanceof LocalizedString) {
      $value = $attribute->getValue()->toArray()[$this->languageMapping[$langcode]] ?? '';
    }
    elseif ($attribute->getValue() instanceof LocalizedEnum) {
      $value = $attribute->getValue()->getLabel()->toArray()[$this->languageMapping[$langcode]] ?? '';
    }
    elseif ($attribute->getValue() instanceof Enum) {
      $value = $attribute->getValue()->getLabel();
    }
    elseif ($attribute->getValue() instanceof AttributeCollection) {
      $value = [];

      foreach ($attribute->getValue() as $key =>  $sub_attribute) {
        /* @var $sub_attribute Attribute */
        $value[$sub_attribute->getName()] = $this->parseAttribute($sub_attribute, $langcode);
      }
    }
    else {
      $value = (string) $this->ctAttribute->getValue();
    }

    return $value;
  }

  public function getId() {
    return $this->ctAttribute->getName();
  }
}
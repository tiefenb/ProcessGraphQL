<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\FieldTrait;
use ProcessWire\Page;

/**
 * Bridge to third-party modules named `GraphQL<FieldtypeClassName>`.
 *
 * Their contract is unchanged and context free:
 * - `public static function getType(Field $field)`
 * - `public static function getInputType(Field $field)` (optional)
 * - `public static function setValue(Page $page, Field $field, $value)`
 */
class FieldtypeThirdParty
{
  use FieldTrait;

  public static function type(Context $context, ?Field $field = null)
  {
    $thirdPartyClassName = self::getThirdPartyClassName($field);
    return $context->types->type(
      "type--{$field->name}",
      fn() => $thirdPartyClassName::getType($field)
    );
  }

  public static function inputField(Context $context, Field $field): array
  {
    return $context->types->field("input--{$field->name}", function () use ($context, $field) {
      $fieldSettings = self::field($context, $field);
      $thirdPartyClassName = self::getThirdPartyClassName($field);
      if ($thirdPartyClassName !== null && method_exists($thirdPartyClassName, 'getInputType')) {
        $fieldSettings['type'] = $thirdPartyClassName::getInputType($field);
      }
      return $fieldSettings;
    });
  }

  public static function setValue(Context $context, Page $page, Field $field, $value): void
  {
    self::getThirdPartyClassName($field)::setValue($page, $field, $value);
  }

  public static function getThirdPartyClassName(Field $field): ?string
  {
    $thirdPartyClassName = "\\ProcessWire\\GraphQL" . $field->type->className();
    return class_exists($thirdPartyClassName) ? $thirdPartyClassName : null;
  }
}

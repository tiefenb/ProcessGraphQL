<?php namespace ProcessWire\GraphQL\Type\Fieldtype\Traits;

use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Utils;

trait FieldTrait
{
  public static function field(Context $context, Field $field): array
  {
    return $context->types->field($field->name, fn() => [
      'name' => $field->name,
      'description' => Utils::fieldDescription($field),
      'type' => static::type($context, $field),
    ]);
  }
}

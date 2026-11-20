<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Type\Definition\Type;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\InputFieldTrait;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\SetValueTrait;
use ProcessWire\GraphQL\Utils;
use ProcessWire\Page;

class FieldtypeFloat
{
  use InputFieldTrait;
  use SetValueTrait;

  public static function type(Context $context, ?Field $field = null): Type
  {
    return Type::float();
  }

  public static function field(Context $context, Field $field): array
  {
    return $context->types->field($field->name, fn() => [
      'name' => $field->name,
      'description' => Utils::fieldDescription($field),
      'type' => self::type($context, $field),
      'resolve' => fn(Page $page) => floatval($page->get($field->name)),
    ]);
  }
}

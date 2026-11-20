<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Type\Definition\CustomScalarType;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\FieldTrait;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\InputFieldTrait;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\SetValueTrait;

class FieldtypeCheckbox
{
  use FieldTrait;
  use InputFieldTrait;
  use SetValueTrait;

  public const NAME = 'Checkbox';

  public const DESCRIPTION = 'An ON/OFF toggle via a single checkbox.';

  public static function type(Context $context, ?Field $field = null): CustomScalarType
  {
    return $context->types->type(self::NAME, fn() => new CustomScalarType([
      'name' => self::NAME,
      'description' => self::DESCRIPTION,
      'serialize' => fn($value) => (bool) $value,
      'parseValue' => fn($value) => (bool) $value,
      'parseLiteral' => fn($valueNode) => (bool) $valueNode->value,
    ]));
  }
}

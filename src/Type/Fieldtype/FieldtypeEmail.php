<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Type\Definition\CustomScalarType;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\FieldTrait;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\InputFieldTrait;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\SetValueTrait;

class FieldtypeEmail
{
  use FieldTrait;
  use InputFieldTrait;
  use SetValueTrait;

  public const NAME = 'Email';

  public const DESCRIPTION = 'E-Mail address in valid format';

  public static function type(Context $context, ?Field $field = null): CustomScalarType
  {
    return $context->types->type(self::NAME, fn() => new CustomScalarType([
      'name' => self::NAME,
      'description' => self::DESCRIPTION,
      'serialize' => fn($value) => $value ? (string) $value : "",
      'parseValue' => fn($value) => (string) $value,
      'parseLiteral' => fn($valueNode) => (string) $valueNode->value,
    ]));
  }
}

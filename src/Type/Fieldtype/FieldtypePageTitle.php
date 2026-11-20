<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Type\Definition\CustomScalarType;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\FieldTrait;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\InputFieldTrait;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\SetValueTrait;

class FieldtypePageTitle
{
  use FieldTrait;
  use InputFieldTrait;
  use SetValueTrait;

  public const NAME = 'PageTitle';

  public const DESCRIPTION = 'Field that stores a page title';

  public static function type(Context $context, ?Field $field = null): CustomScalarType
  {
    return $context->types->type(self::NAME, fn() => new CustomScalarType([
      'name' => self::NAME,
      'description' => self::DESCRIPTION,
      'serialize' => fn($value) => (string) $value,
      'parseValue' => fn($value) => (string) $value,
      'parseLiteral' => fn($valueNode) => (string) $valueNode->value,
    ]));
  }
}

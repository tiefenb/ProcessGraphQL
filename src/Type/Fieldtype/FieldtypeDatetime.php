<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use DateTime;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Type;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\InputFieldTrait;
use ProcessWire\GraphQL\Utils;
use ProcessWire\Page;

class FieldtypeDatetime
{
  use InputFieldTrait;

  public const NAME = 'DateTime';

  public const DESCRIPTION = 'A string that represends a date and optionally time.';

  public static function type(Context $context, ?Field $field = null): CustomScalarType
  {
    return $context->types->type(self::NAME, fn() => new CustomScalarType([
      'name' => self::NAME,
      'description' => self::DESCRIPTION,
      'serialize' => fn($value) => (string) $value,
      'parseValue' => fn($value) => new DateTime($value),
      'parseLiteral' => fn($valueNode) => (string) $valueNode->value,
    ]));
  }

  public static function field(Context $context, Field $field): array
  {
    return $context->types->field($field->name, fn() => [
      'name' => $field->name,
      'description' => Utils::fieldDescription($field),
      'type' => self::type($context, $field),
      'args' => [
        'format' => [
          'type' => Type::string(),
          'description' => "PHP date formatting string. Refer to https://devdocs.io/php/function.date",
        ],
      ],
      'resolve' => function (Page $page, array $args) use ($field) {
        $name = $field->name;

        if (isset($args['format'])) {
          $rawValue = $page->getUnformatted($name);
          return $rawValue ? date($args['format'], $rawValue) : "";
        }

        return $page->$name;
      }
    ]);
  }

  public static function setValue(Context $context, Page $page, Field $field, $value): void
  {
    $fieldName = $field->name;
    $page->$fieldName = $value instanceof \DateTimeInterface
      ? $value->format('Y-m-d H:i:s')
      : $value;
  }
}

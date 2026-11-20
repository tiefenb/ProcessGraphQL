<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\SetValueTrait;
use ProcessWire\GraphQL\Utils;
use ProcessWire\InputfieldSelectMultiple;
use ProcessWire\Page;

class FieldtypeOptions
{
  use SetValueTrait;

  public const NAME = 'FieldtypeOptions';

  public const INPUT_NAME = 'FieldtypeOptionsInput';

  public const DESCRIPTION = 'Field that stores single and multi select options.';

  public static function type(Context $context, ?Field $field = null): ObjectType|ListOfType
  {
    $type = $context->types->type(self::NAME, fn() => new ObjectType([
      'name' => self::NAME,
      'description' => self::DESCRIPTION,
      'fields' => [
        [
          'name' => 'title',
          'type' => Type::nonNull(Type::string()),
          'description' => 'The title of the selected option.',
          'resolve' => fn($value) => $value->title,
        ],
        [
          'name' => 'value',
          'type' => Type::string(),
          'description' => 'The value of the selected option.',
          'resolve' => fn($value) => $value->value,
        ],
        [
          'name' => 'id',
          'type' => Type::nonNull(Type::int()),
          'description' => 'The id of the selected option.',
          'resolve' => fn($value) => $value->id,
        ],
      ],
    ]));

    return $field && self::isMultiple($field) ? Type::listOf($type) : $type;
  }

  public static function field(Context $context, Field $field): array
  {
    return $context->types->field($field->name, fn() => [
      'name' => $field->name,
      'description' => Utils::fieldDescription($field),
      'type' => self::type($context, $field),
      'resolve' => function (Page $page) use ($field) {
        $value = $page->get($field->name);

        // If a single option field does not have an id, then it means it's empty.
        if (!self::isMultiple($field) && !$value->id) {
          return null;
        }

        return $value;
      }
    ]);
  }

  public static function inputType(Context $context, Field $field): EnumType|ListOfType
  {
    return $context->types->type(self::getName($field), function () use ($field) {
      $type = new EnumType([
        'name' => self::getName($field),
        'description' => "Possible values for the " . self::NAME . ".",
        // lazy: the options are only loaded from the database when the enum
        // is actually used by an operation (mutation input or introspection)
        'values' => static function () use ($field): array {
          $options = [];
          foreach ($field->type->getOptions($field) as $option) {
            $key = $option->title ?: $option->value;
            $options[$key] = [
              'value' => $key,
              'description' => $option->value,
            ];
          }
          return $options;
        },
      ]);

      return self::isMultiple($field) ? Type::listOf($type) : $type;
    });
  }

  public static function isMultiple(Field $field): bool
  {
    $inputfieldClassName = 'ProcessWire\\' . $field->inputfieldClass;
    return is_subclass_of($inputfieldClassName, InputfieldSelectMultiple::class)
      || $inputfieldClassName === InputfieldSelectMultiple::class;
  }

  public static function inputField(Context $context, Field $field): array
  {
    return $context->types->field("input--{$field->name}", fn() => [
      'name' => $field->name,
      'description' => Utils::fieldDescription($field),
      'type' => self::inputType($context, $field),
    ]);
  }

  public static function getName(?Field $field = null): string
  {
    if ($field instanceof Field) {
      return Utils::normalizeTypeName($field->name . self::INPUT_NAME);
    }

    return self::INPUT_NAME;
  }
}

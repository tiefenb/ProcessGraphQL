<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Type\Definition\CustomScalarType;
use ProcessWire\GraphQL\Type\Resolver;
use ProcessWire\GraphQL\Type\CacheTrait;

class FieldtypeDatetime
{ 
  use CacheTrait;
  public static function type()
  {
    return self::cache('default', function () {
      return new CustomScalarType([
        'name' => 'Datetime',
        'description' => 'A date and optionally time',
        'serialize' => function ($value) {
          return (string) $value;
        },
        'parseValue' => function ($value) {
          return (string) $value;
        },
        'parseLiteral' => function ($valueNode) {
          return (string) $valueNode->value;
        },
      ]);
    });
  }

  public static function field($options)
  {
    return self::cache('field-' . $options['name'], function () use ($options) {
      return array_merge(
        Resolver::resolveWithDateFormatter($options),
        ['type' => self::type()]
      );
    });
  }
}
<?php namespace ProcessWire\GraphQL\Field\Debug;

use GraphQL\Type\Definition\Type;
use ProcessWire\GraphQL\Context;

class DbQuery
{
  public static function field(): array
  {
    return [
      'type' => Type::listOf(Type::string()),
      'name' => 'dbQuery',
      'description' => 'The queries made to database to fulfill this request. Available in debug mode.',
      'resolve' => fn($root, array $args, Context $context) => $context->database()->queryLog(),
    ];
  }
}

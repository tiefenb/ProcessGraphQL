<?php namespace ProcessWire\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Fieldtype\FieldtypeEmail;

class UserType
{
  public const NAME = 'User';

  public const DESCRIPTION = 'ProcessWire User.';

  public static function type(Context $context): ObjectType
  {
    return $context->types->type(self::NAME, fn() => new ObjectType([
      'name' => self::NAME,
      'description' => self::DESCRIPTION,
      'fields' => fn() => [
        'name' => [
          'type' => Type::nonNull(Type::string()),
          'description' => "The user's login name.",
        ],
        'email' => [
          'type' => FieldtypeEmail::type($context),
          'description' => "The user's email address.",
        ],
        'id' => [
          'type' => Type::nonNull(Type::id()),
          'description' => "The user's id.",
        ],
      ],
    ]));
  }
}

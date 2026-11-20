<?php namespace ProcessWire\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use ProcessWire\GraphQL\Context;

class AuthResponseType
{
  public const NAME = 'AuthResponse';

  public static function type(Context $context): ObjectType
  {
    return $context->types->type(self::NAME, fn() => new ObjectType([
      'name' => self::NAME,
      'description' => 'Object type that represents the authentication response.',
      'fields' => [
        'statusCode' => [
          'type' => Type::nonNull(Type::int()),
          'description' => 'The authentication status code. E.g. 200 if successful.',
        ],
        'message' => [
          'type' => Type::nonNull(Type::string()),
          'description' => 'Human readable message of the authentication response. E.g. "successful login!"',
        ],
      ],
    ]));
  }
}

<?php namespace ProcessWire\GraphQL\Field\Auth;

use GraphQL\Type\Definition\Type;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\AuthResponseType;

class Login
{
  public static function field(Context $context): array
  {
    return [
      'type' => AuthResponseType::type($context),
      'name' => 'login',
      'description' => 'Allows you to authenticate into the app.',
      'args' => [
        'name' => Type::string(),
        'pass' => Type::string(),
      ],
      'resolve' => function ($root, array $args, Context $context) {
        $user = $context->session()->login($args['name'] ?? '', $args['pass'] ?? '');
        return $user === null
          ? ['statusCode' => 401, 'message' => 'Wrong name and/or pass.']
          : ['statusCode' => 200, 'message' => 'Successful login!'];
      }
    ];
  }
}

<?php namespace ProcessWire\GraphQL\Field\Auth;

use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\AuthResponseType;

class Logout
{
  public static function field(Context $context): array
  {
    return [
      'type' => AuthResponseType::type($context),
      'name' => 'logout',
      'description' => 'Allows you to logout from the app.',
      'resolve' => function ($root, array $args, Context $context) {
        try {
          $context->session()->logout();
          return ['statusCode' => 200, 'message' => 'Successful logout!'];
        } catch (\Throwable $error) {
          return ['statusCode' => 500, 'message' => 'Could not logout: ' . $error->getMessage()];
        }
      }
    ];
  }
}

<?php

namespace ProcessWire\GraphQL\Test;

use GraphQL\Type\Definition\Type;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Utils;

class ContextTest extends GraphQLTestCase
{
  const settings = [
    "legalTemplates" => ["skyscraper"],
  ];

  public function testResolversAndHooksReceiveContext()
  {
    $hookContext = null;
    $hook = Utils::wire()->addHookAfter("ProcessGraphQL::getQueryFields", function ($event) use (&$hookContext) {
      $hookContext = $event->arguments(1);
      $fields = $event->return;
      $fields[] = [
        "name" => "contextClass",
        "type" => Type::string(),
        "resolve" => fn($root, array $args, $context) => get_class($context),
      ];
      $event->return = $fields;
    });

    try {
      $res = self::execute("{ contextClass }");
    } finally {
      Utils::wire()->removeHook($hook);
    }

    self::assertObjectNotHasProperty("errors", $res);
    self::assertEquals(Context::class, $res->data->contextClass);
    self::assertInstanceOf(Context::class, $hookContext);
    self::assertSame(Utils::module(), $hookContext->module);
  }

  public function testSchemaFollowsTheCurrentUser()
  {
    $query = "{ skyscraper(s: \"limit=1\") { list { id } } }";

    // guest has no access to the skyscraper template
    $res = self::execute($query);
    self::assertTrue(isset($res->errors), "Guest must not see skyscrapers.");

    // after login the schema of the same process includes it
    Utils::session()->login("admin", Utils::config()->testUsers["admin"]);
    try {
      $res = self::execute($query);
      self::assertObjectNotHasProperty("errors", $res);
      self::assertCount(1, $res->data->skyscraper->list);
    } finally {
      Utils::session()->logout();
    }

    // and it is gone again after logout
    $res = self::execute($query);
    self::assertTrue(isset($res->errors), "Guest must not see skyscrapers after logout.");
  }

  public function testEveryExecutionHasItsOwnContext()
  {
    $module = Utils::module();
    $a = $module->createContext();
    $b = $module->createContext();

    self::assertNotSame($a, $b);
    self::assertNotSame($a->types, $b->types);
    self::assertNotSame($a->buffer, $b->buffer);
    self::assertNotSame($a->permissions, $b->permissions);
    self::assertSame(Utils::wire(), $a->wire);
  }
}

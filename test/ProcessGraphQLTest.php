<?php

namespace ProcessWire\GraphQL\Test;

use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use GraphQL\Type\Definition\Type;
use ProcessWire\GraphQL\Test\GraphQLTestCase;
use ProcessWire\GraphQL\Utils;

class ProcessGraphQLTest extends GraphQLTestCase
{
  private array $server = [];

  public function setUp(): void
  {
    $this->server = $_SERVER;
  }

  public function tearDown(): void
  {
    $_POST = [];
    $_GET = [];
    $_SERVER = $this->server;
    Utils::module()->queryBatching = false;
  }

  private static function request(string $method, string $contentType = ''): void
  {
    $_SERVER["REQUEST_METHOD"] = $method;
    $_SERVER["CONTENT_TYPE"] = $contentType;
  }

  public function testGetGraphQLServerUrl()
  {
    $module = Utils::module();
    $default = "/processwire/setup/graphql/";
    self::assertEquals($default, $module->getGraphQLServerUrl());

    $custom = "/custom/graphql/server/path/";
    $module->GraphQLServerUrl = $custom;
    self::assertEquals($custom, $module->getGraphQLServerUrl());
  }

  public function testGetRequestForPostForm()
  {
    self::request("POST", "application/x-www-form-urlencoded");

    // query is empty by default
    $request = Utils::module()->getRequest();
    self::assertInstanceOf(OperationParams::class, $request);
    self::assertNull($request->query);
    self::assertNull($request->variables);

    // `payload` is still supported as alias of `query`
    $_POST["payload"] = "{ me { name } }";
    self::assertEquals("{ me { name } }", Utils::module()->getRequest()->query);

    // `query` wins over `payload`
    $_POST["query"] = "{ me { id } }";
    self::assertEquals("{ me { id } }", Utils::module()->getRequest()->query);

    // variables are decoded from JSON
    $_POST["variables"] = '{ "one": 1, "two": "two" }';
    self::assertEquals(["one" => 1, "two" => "two"], Utils::module()->getRequest()->variables);
  }

  public function testGetRequestForJson()
  {
    self::request("POST", "application/json");
    $body = '{ "query": "query q($id: ID) { me { id } }", "variables": { "id": 1 }, "operationName": "q" }';

    $request = Utils::module()->getRequest(fn() => $body);
    self::assertEquals("query q(\$id: ID) { me { id } }", $request->query);
    self::assertEquals(["id" => 1], $request->variables);
    self::assertEquals("q", $request->operation);
    self::assertFalse($request->readOnly);
  }

  public function testGetRequestForInvalidJson()
  {
    self::request("POST", "application/json");
    $this->expectException(RequestError::class);
    Utils::module()->getRequest(fn() => "{ not json");
  }

  public function testGetRequestForGetIsReadOnly()
  {
    self::request("GET");
    $_GET["query"] = "{ me { name } }";

    $request = Utils::module()->getRequest();
    self::assertEquals("{ me { name } }", $request->query);
    self::assertTrue($request->readOnly, "GET requests must not run mutations.");
  }

  public function testGetRequestForBatch()
  {
    self::request("POST", "application/json");
    $body = '[{ "query": "{ me { name } }" }, { "payload": "{ me { id } }" }]';

    $requests = Utils::module()->getRequest(fn() => $body);
    self::assertCount(2, $requests);
    self::assertEquals("{ me { name } }", $requests[0]->query);
    self::assertEquals("{ me { id } }", $requests[1]->query);
  }

  public function testExecuteGraphQL()
  {
    self::request("POST", "application/x-www-form-urlencoded");

    // returns GraphQL response with errors when no query is provided
    $res = self::execute();
    self::assertStringContainsString(
      'GraphQL Request must include at least one of those two parameters: "query" or "queryId"',
      $res->errors[0]->message
    );

    // accepts GraphQL request via arguments
    $payload = "{ me { name } }";
    $res = self::execute($payload);
    self::assertEquals("guest", $res->data->me->name, "Accepts request via arguments");
    self::assertObjectNotHasProperty("errors", $res, "There are errors.");

    // accepts GraphQL request via $_POST variable
    $_POST["payload"] = $payload;
    $res = self::execute();
    self::assertEquals("guest", $res->data->me->name, 'Accepts request via $_POST variable');
    self::assertObjectNotHasProperty("errors", $res, "There are errors.");
  }

  public function testMutationViaGetIsRejected()
  {
    self::request("GET");
    $_GET["query"] = "mutation { zombie }";

    $res = self::execute();
    self::assertEquals("GET supports only query operation", $res->errors[0]->message);
  }

  public function testBatchingIsDisabledByDefault()
  {
    self::request("POST", "application/json");
    $_POST = [];
    $module = Utils::module();
    $batch = [
      OperationParams::create(["query" => "{ me { name } }"]),
      OperationParams::create(["query" => "{ me { id } }"]),
    ];

    $res = $module->createServer($module->createContext())->executeRequest($batch);
    self::assertStringContainsString("Batched queries are not supported", $res[0]->toArray()["errors"][0]["message"]);

    $module->queryBatching = true;
    $res = $module->createServer($module->createContext())->executeRequest($batch);
    self::assertEquals("guest", $res[0]->toArray()["data"]["me"]["name"]);
    self::assertArrayHasKey("id", $res[1]->toArray()["data"]["me"]);
  }

  public function testGetQueryFieldsHook()
  {
    $hook = Utils::wire()->addHookAfter("ProcessGraphQL::getQueryFields", function (
      $event
    ) {
      $fields = $event->return;
      $fields[] = [
        "name" => "hello",
        "type" => Type::string(),
        "resolve" => function () {
          return "world!";
        },
      ];
      $event->return = $fields;
    });

    $res = self::execute("{ hello }");
    Utils::wire()->removeHook($hook);
    self::assertEquals("world!", $res->data->hello);
    self::assertObjectNotHasProperty("errors", $res, "There are errors.");
  }

  public function testGetMutationFieldsHook()
  {
    $hook = Utils::wire()->addHookAfter("ProcessGraphQL::getMutationFields", function (
      $event
    ) {
      $fields = $event->return;
      $fields[] = [
        "name" => "zombie",
        "type" => Type::string(),
        "resolve" => function () {
          return "apocalypse";
        },
      ];
      $event->return = $fields;
    });

    $res = self::execute("mutation { zombie }");
    Utils::wire()->removeHook($hook);
    self::assertEquals("apocalypse", $res->data->zombie);
    self::assertObjectNotHasProperty("errors", $res, "There are errors.");
  }
}

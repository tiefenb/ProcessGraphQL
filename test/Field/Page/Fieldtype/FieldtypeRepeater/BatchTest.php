<?php

namespace ProcessWire\GraphQL\Test\FieldtypeRepeater;

use ProcessWire\GraphQL\Test\GraphQLTestCase;
use ProcessWire\GraphQL\Utils;

class BatchTest extends GraphQLTestCase
{
  const settings = [
    "login" => "admin",
    "legalTemplates" => ["list-all"],
    "legalFields" => ["slides", "title", "body"],
  ];

  private static array $created = [];

  public static function tearDownAfterClass(): void
  {
    foreach (self::$created as $page) {
      Utils::pages()->delete($page, true);
    }
    self::$created = [];
    parent::tearDownAfterClass();
  }

  private static function createPage(int $slides): int
  {
    $pages = Utils::pages();
    $page = $pages->newPage(["template" => "list-all", "parent" => $pages->get("/")]);
    $page->of(false);
    $page->title = "Repeater batch " . count(self::$created);
    $page->save();
    for ($i = 0; $i < $slides; $i++) {
      $slide = $page->slides->getNew();
      $slide->title = "Slide $i of {$page->id}";
      $slide->body = "Body $i of {$page->id}";
      $slide->save();
      $page->slides->add($slide);
    }
    $page->save();
    self::$created[] = $page;
    return $page->id;
  }

  private static function runQuery(array $ids): array
  {
    return self::executeAndCountQueries('{
      listAll(s: "id=' . implode("|", $ids) . ', sort=id") {
        list { id slides { list { id title body } } }
      }
    }');
  }

  public function testRepeaterItemsAreLoadedInBatches()
  {
    $counts = [2, 0, 3, 1, 2, 1];
    $ids = array_map(fn($count) => self::createPage($count), $counts);

    [$res, $queriesForTwo] = self::runQuery(array_slice($ids, 0, 2));
    self::assertObjectNotHasProperty("errors", $res);

    [$res, $queriesForSix] = self::runQuery($ids);
    self::assertObjectNotHasProperty("errors", $res);

    self::assertCount(6, $res->data->listAll->list);
    foreach ($res->data->listAll->list as $i => $item) {
      self::assertEquals($ids[$i], $item->id);
      self::assertCount($counts[$i], $item->slides->list, "Page $i has its own slides.");
      foreach ($item->slides->list as $n => $slide) {
        self::assertEquals("Slide $n of {$item->id}", $slide->title, "Slides keep their page and order.");
        self::assertStringContainsString("Body $n of {$item->id}", $slide->body);
      }
    }

    self::assertEquals(
      $queriesForTwo,
      $queriesForSix,
      "The number of database queries must not grow with the number of pages ($queriesForTwo for 2, $queriesForSix for 6)."
    );
  }
}

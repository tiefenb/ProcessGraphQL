<?php

namespace ProcessWire\GraphQL\Test\FieldtypeImage;

use ProcessWire\GraphQL\Test\GraphQLTestCase;
use ProcessWire\GraphQL\Utils;

class BatchTest extends GraphQLTestCase
{
  const settings = [
    "login" => "admin",
    "legalTemplates" => ["skyscraper"],
    "legalFields" => ["images"],
  ];

  private static function runQuery(string $ids): array
  {
    return self::executeAndCountQueries('{
      skyscraper(s: "id=' . $ids . '") {
        list { id images { url } }
      }
    }');
  }

  public function testQueryCountDoesNotGrowWithTheNumberOfParents()
  {
    // one skyscraper with images for every city
    $perCity = [];
    foreach (Utils::pages()->find("template=skyscraper, images.count>0, status<trash") as $skyscraper) {
      $perCity[$skyscraper->parent_id] ??= $skyscraper->id;
    }
    self::assertGreaterThan(3, count($perCity), "Needs skyscrapers of several cities.");

    [$res, $queriesForTwo] = self::runQuery(implode("|", array_slice($perCity, 0, 2)));
    self::assertObjectNotHasProperty("errors", $res);
    self::assertCount(2, $res->data->skyscraper->list);

    [$res, $queriesForAll] = self::runQuery(implode("|", $perCity));
    self::assertObjectNotHasProperty("errors", $res);
    self::assertCount(count($perCity), $res->data->skyscraper->list);
    foreach ($res->data->skyscraper->list as $skyscraper) {
      self::assertNotEmpty($skyscraper->images[0]->url);
    }

    self::assertEquals(
      $queriesForTwo,
      $queriesForAll,
      "The number of database queries must not grow with the number of parent pages" .
        " ($queriesForTwo for 2 cities, $queriesForAll for " . count($perCity) . ")."
    );
  }
}

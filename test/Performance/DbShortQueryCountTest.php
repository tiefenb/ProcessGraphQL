<?php

namespace ProcessWire\GraphQL\Test\Performance;

use ProcessWire\GraphQL\Test\GraphQLTestCase;
use ProcessWire\GraphQL\Utils;

#[\PHPUnit\Framework\Attributes\Group('performance')]
class DbShortQueryCountTest extends GraphQLTestCase
{
  public static function getSettings()
  {
    return [
      "login" => "admin",
      "legalTemplates" => ["architect", "skyscraper"],
      "legalFields" => [
        "title",
        "options",
        "images",
        "architects",
        "born",
        "resume",
        "height",
        "floors",
        "year",
      ],
      "maxLimit" => 1000,
    ];
  }

  public static $shortQuery = [
    "query" => 'query getSkyscrapers($selector: Selector!) {
      skyscraper(s: $selector) {
        getTotal
        list{
          id
          title
        }
      }
    }',
    "variables" => [
      "selector" => "architects.count>1",
    ],
  ];

  public function testShortVsLongQuery()
  {
    $query = self::$shortQuery;
    [$res, $queryCount] = self::executeAndCountQueries($query["query"], $query["variables"]);

    \ProcessWire\GraphQL\log($queryCount, "ShortQueryCount");
    self::assertObjectNotHasProperty("errors", $res, "There are errors.");
    self::assertGreaterThan(0, $queryCount, "No database queries were counted.");
  }
}

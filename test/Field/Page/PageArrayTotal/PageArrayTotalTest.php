<?php

namespace ProcessWire\GraphQL\Test\Field\PageArrayTotal;

use ProcessWire\GraphQL\Utils;
use ProcessWire\GraphQL\Test\GraphQLTestCase;

/**
 * The total is only counted when `getTotal` is requested. Make sure it is
 * still correct then, and that `list` is unaffected when it is not requested.
 */
class PageArrayTotalTest extends GraphQLTestCase
{
  const settings = [
    "login" => "admin",
    "legalTemplates" => ["city"],
  ];

  public function testTotalWithLimit()
  {
    $total = Utils::pages()->count("template=city");
    self::assertGreaterThan(2, $total);

    $res = self::execute('{ city(s: "limit=2") { getTotal getLimit list { id } } }');
    self::assertObjectNotHasProperty("errors", $res);
    self::assertEquals($total, $res->data->city->getTotal);
    self::assertEquals(2, $res->data->city->getLimit);
    self::assertCount(2, $res->data->city->list);
  }

  public function testListWithoutTotal()
  {
    $res = self::execute('{ city(s: "limit=2") { list { id } } }');
    self::assertObjectNotHasProperty("errors", $res);
    self::assertCount(2, $res->data->city->list);
  }
}

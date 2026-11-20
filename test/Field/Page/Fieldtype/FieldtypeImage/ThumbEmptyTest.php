<?php

/**
 * `size` without width and height returns an empty image whose
 * `variations` must resolve to an empty list instead of an error.
 */

namespace ProcessWire\GraphQL\Test\FieldtypeImage;

use ProcessWire\GraphQL\Test\GraphQLTestCase;

class ThumbEmptyTest extends GraphQLTestCase
{
  const settings = [
    "login" => "admin",
    "legalTemplates" => ["skyscraper"],
    "legalFields" => ["images"],
    "legalPageImageFields" => ["size", "variations", "width"],
    "legalPageFileFields" => ["url"],
  ];

  const PAGE_ID = 4182;

  public function testEmptyImageVariations()
  {
    $query = "{
      skyscraper (s: \"id=" . self::PAGE_ID . "\") {
        list {
          images {
            size {
              width
              variations {
                width
              }
            }
          }
        }
      }
    }";

    $res = self::execute($query);
    self::assertObjectNotHasProperty("errors", $res, "There are errors.");
    $size = $res->data->skyscraper->list[0]->images[0]->size;
    self::assertEquals(0, $size->width);
    self::assertSame([], $size->variations);
  }
}

<?php namespace ProcessWire\GraphQL\Test\Permissions\Editor\View;

use ProcessWire\GraphQL\Test\GraphqlTestCase;
use ProcessWire\GraphQL\Utils;
use ProcessWire\Page;

class ForbiddenSelectorsTest extends GraphqlTestCase
{
  /**
   * + For editor with view access only.
   * - Selectors that change page visibility (status, include, check_access)
   *   must not be usable to reveal unpublished or trashed pages.
   */
  public static function getSettings()
  {
    return [
      "login" => "editor",
      "legalTemplates" => ["skyscraper"],
      "legalFields" => ["title"],
      "access" => [
        "templates" => [
          [
            "name" => "skyscraper",
            "roles" => ["editor"],
          ],
        ],
        "fields" => [
          [
            "name" => "title",
            "viewRoles" => ["editor"],
          ],
        ],
      ],
    ];
  }

  public static function forbiddenSelectorProvider(): array
  {
    return [
      "status=" => ["status=unpublished"],
      "status>=" => ["status>=unpublished"],
      "status trash" => ["status=trash"],
      "include" => ["include=all"],
      "check_access" => ["check_access=0"],
      "checkAccess alias" => ["checkAccess=0, status=unpublished"],
      "OR-field" => ["status|id=2048"],
      "sub-selector" => ["id=[template=skyscraper, include=all]"],
      "OR-group" => ["(status=unpublished), (id>0)"],
    ];
  }

  /**
   * @dataProvider forbiddenSelectorProvider
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenSelectorProvider')]
  public function testForbiddenSelector(string $selector)
  {
    $query = 'query getSkyscrapers($s: Selector){
      skyscraper(s: $s) {
        list {
          id
        }
      }
    }';
    $res = self::execute($query, ["s" => $selector]);
    self::assertTrue(isset($res->errors), "Selector `$selector` must be rejected.");
    self::assertStringContainsString("is not allowed", $res->errors[0]->message);
  }

  public function testUnpublishedPageIsNotReturned()
  {
    $pages = Utils::pages();
    $skyscraper = $pages->get("template=skyscraper, sort=random");
    $skyscraper->of(false);
    $skyscraper->addStatus(Page::statusUnpublished);
    $pages->save($skyscraper, ["quiet" => true]);

    $query = "{
      skyscraper(s: \"id={$skyscraper->id}\") {
        list {
          id
        }
      }
    }";
    $res = self::execute($query);
    self::assertObjectNotHasProperty("errors", $res);
    self::assertCount(0, $res->data->skyscraper->list);

    $skyscraper->removeStatus(Page::statusUnpublished);
    $pages->save($skyscraper, ["quiet" => true]);
  }
}

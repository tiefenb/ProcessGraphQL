<?php namespace ProcessWire\GraphQL\Test\Permissions\Editor\Update\NotAllowed;

use ProcessWire\GraphQL\Test\GraphqlTestCase;
use ProcessWire\GraphQL\Utils;

class ForeignTemplateTest extends GraphqlTestCase
{
  /**
   * + For editor.
   * + The user has edit permission for the "skyscraper" template.
   * - The target page uses a different template ("city", not legal).
   */
  public static function getSettings()
  {
    return [
      "login" => "editor",
      "legalTemplates" => ["skyscraper"],
      "access" => [
        "templates" => [
          [
            "name" => "skyscraper",
            "roles" => ["editor"],
            "editRoles" => ["editor"],
          ],
        ],
      ],
    ];
  }

  public function testCannotUpdatePageOfOtherTemplate()
  {
    $city = Utils::pages()->get("template=city, sort=random");
    $originalName = $city->name;

    $query = 'mutation updatePage($page: SkyscraperUpdateInput!){
      updateSkyscraper(page: $page) {
        name
      }
    }';
    $variables = [
      "page" => [
        "id" => $city->id,
        "name" => "hijacked-city-name",
      ],
    ];

    $res = self::execute($query, $variables);
    self::assertEquals(1, count($res->errors), "Does not allow to update a page of another template.");
    self::assertEquals(
      $originalName,
      Utils::pages()->getFresh($city->id)->name,
      "The page of another template stays untouched."
    );
  }

  public function testCannotUpdateSystemPage()
  {
    $setup = Utils::pages()->get("template=admin, name=setup");

    $query = 'mutation updatePage($page: SkyscraperUpdateInput!){
      updateSkyscraper(page: $page) {
        name
      }
    }';
    $variables = [
      "page" => [
        "id" => $setup->id,
        "name" => "hijacked-setup",
      ],
    ];

    $res = self::execute($query, $variables);
    self::assertEquals(1, count($res->errors), "Does not allow to update admin pages.");
    self::assertEquals("setup", Utils::pages()->getFresh($setup->id)->name);
  }
}

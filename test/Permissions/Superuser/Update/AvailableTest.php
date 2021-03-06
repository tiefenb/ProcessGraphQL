<?php namespace ProcessWire\GraphQL\Test\Permissions\Superuser\Update;

use ProcessWire\GraphQL\Test\GraphqlTestCase;

class AvailableTest extends GraphqlTestCase
{
  /**
   * + For superuser.
   * + The target template is legal.
   */
  public static function getSettings()
  {
    return [
      "login" => "admin",
      "legalTemplates" => ["city"],
    ];
  }

  public function testPermission()
  {
    assertTypePathExists(
      ["Mutation", "updateCity"],
      "The update field should be available for superuser if the target template is legal."
    );
  }
}

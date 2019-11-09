<?php namespace ProcessWire\GraphQL\Test\Permissions;

use ProcessWire\GraphQL\Test\GraphqlTestCase;

use function ProcessWire\GraphQL\Test\Assert\assertTypePathExists;

class EditorTrashAvailableTest extends GraphqlTestCase {

  /**
   * + For Superuser
   * + The template is legal.
   */
  public static function getSettings()
  {
    return [
      'login' => 'editor',
      'legalTemplates' => ['skyscraper'],
    ];
  }

  public function testPermission() {
    assertTypePathExists(['Mutation', 'trash']);
  }
}
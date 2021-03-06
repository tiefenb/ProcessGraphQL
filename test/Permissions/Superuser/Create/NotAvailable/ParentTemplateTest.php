<?php namespace ProcessWire\GraphQL\Test\Permissions\Superuser\Create\NotAvailable;

use ProcessWire\GraphQL\Test\GraphqlTestCase;

class ParentTemplateTest extends GraphqlTestCase
{
  /**
   * + For superuser.
   * + The template is legal.
   * + All the required fields are legal.
   * - But the configured parent template is not legal.
   */
  const settings = [
    "login" => "admin",
    "legalTemplates" => ["skyscraper"],
    "legalFields" => ["title"],
  ];

  public function testPermission()
  {
    assertTypePathNotExists(
      ["Mutation", "createSkyscraper"],
      "Create field should not be available if configured parent template is not legal."
    );
  }
}

<?php namespace ProcessWire\GraphQL\Type\Fieldtype\Traits;

use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\Page;

trait SetValueTrait
{
  public static function setValue(Context $context, Page $page, Field $field, $value): void
  {
    $fieldName = $field->name;
    $page->$fieldName = $value;
  }
}

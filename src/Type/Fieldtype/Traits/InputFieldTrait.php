<?php namespace ProcessWire\GraphQL\Type\Fieldtype\Traits;

use ProcessWire\Field;
use ProcessWire\GraphQL\Context;

trait InputFieldTrait
{
  public static function inputField(Context $context, Field $field): array
  {
    return static::field($context, $field);
  }
}

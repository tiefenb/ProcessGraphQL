<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\Type;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\ImageType;

/**
 * Images are loaded in batches like files, only the GraphQL type differs.
 */
class FieldtypeImage extends FieldtypeFile
{
  public static function type(Context $context, ?Field $field = null): ListOfType
  {
    return Type::listOf(ImageType::type($context));
  }
}

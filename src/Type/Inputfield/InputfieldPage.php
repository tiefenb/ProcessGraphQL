<?php namespace ProcessWire\GraphQL\Type\Inputfield;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use ProcessWire\Field;
use ProcessWire\FieldtypePage;
use ProcessWire\GraphQL\Context;
use ProcessWire\Page;

class InputfieldPage
{
  public const NAME = 'InputfieldPage';

  public static function getName(): string
  {
    return self::NAME;
  }

  public static function getDescription(?Field $field = null): string
  {
    return (string) ($field?->description ?? '');
  }

  public static function type(Context $context, ?Field $field = null): InputObjectType
  {
    return $context->types->type(self::NAME, fn() => new InputObjectType([
      'name' => self::NAME,
      'fields' => [
        'add' => [
          'type' => Type::listOf(Type::id()),
          'description' => 'List of page ids that you would like to add.',
        ],
        'remove' => [
          'type' => Type::listOf(Type::id()),
          'description' => 'List of page ids that you would like to remove.',
        ],
      ],
    ]));
  }

  public static function inputField(Context $context, Field $field): array
  {
    return [
      'name' => $field->name,
      'description' => self::getDescription($field),
      'type' => self::type($context, $field),
    ];
  }

  public static function setValue(Context $context, Page $page, Field $field, $value): void
  {
    $fieldName = $field->name;
    $pages = $context->pages();

    // temporarily read the value as PageArray, without changing the
    // field setting for the rest of the request
    $derefAsPage = $field->derefAsPage;
    $field->derefAsPage = FieldtypePage::derefAsPageArray;
    try {
      $fieldValue = $page->$fieldName;
    } finally {
      $field->derefAsPage = $derefAsPage;
    }

    foreach ($value['add'] ?? [] as $id) {
      $fieldValue->add($pages->get($id));
    }
    foreach ($value['remove'] ?? [] as $id) {
      $fieldValue->remove($pages->get($id));
    }
    $page->$fieldName = $fieldValue;
  }
}

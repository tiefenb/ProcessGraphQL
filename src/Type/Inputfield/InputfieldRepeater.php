<?php namespace ProcessWire\GraphQL\Type\Inputfield;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\InputType\PageCreateInputType;
use ProcessWire\GraphQL\InputType\RepeaterCreateInputType;
use ProcessWire\GraphQL\InputType\RepeaterUpdateInputType;
use ProcessWire\GraphQL\Utils;
use ProcessWire\NullPage;
use ProcessWire\Page;

class InputfieldRepeater
{
  public static function getName(Field $field): string
  {
    return Utils::normalizeTypeName("{$field->name}RepeaterInput");
  }

  public static function getDescription(?Field $field = null): string
  {
    return (string) ($field?->description ?? '');
  }

  public static function type(Context $context, Field $field): InputObjectType
  {
    return $context->types->type(self::getName($field), function () use ($context, $field) {
      $template = $context->templates()->get($field->get('template_id'));
      return new InputObjectType([
        'name' => self::getName($field),
        'fields' => fn() => [
          'add' => [
            'type' => Type::listOf(RepeaterCreateInputType::type($context, $template)),
            'description' => 'List of values that you want to add into your repeater field.',
          ],
          'remove' => [
            'type' => Type::listOf(Type::id()),
            'description' => "List of repeater items' ids that you would like to remove.",
          ],
          'update' => [
            'type' => Type::listOf(RepeaterUpdateInputType::type($context, $template)),
            'description' => 'List of values for repeater items that you would like to update.',
          ],
        ],
      ]);
    });
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

    // create repeaters
    foreach ($value['add'] ?? [] as $createValues) {
      $r = $page->$fieldName->getNew();
      PageCreateInputType::setValues($context, $r, $createValues);
      $r->save();
    }

    // remove repeaters
    foreach ($value['remove'] ?? [] as $id) {
      $r = $page->$fieldName->get("id=" . (int) $id);
      // skip if the repeater item cannot be found
      if ($r instanceof NullPage) {
        continue;
      }
      $page->$fieldName->remove($r);
    }

    // update repeaters
    foreach ($value['update'] ?? [] as $updateValues) {
      $r = $page->$fieldName->get("id=" . (int) $updateValues['id']);

      // skip if the repeater item cannot be found
      if ($r instanceof NullPage) {
        continue;
      }
      $r->of(false);

      // unset the id from updateValues and set the new values
      unset($updateValues['id']);
      PageCreateInputType::setValues($context, $r, $updateValues);

      $r->save();
    }
  }
}

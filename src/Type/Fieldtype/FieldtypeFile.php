<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\Type;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\FileType;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\InputFieldTrait;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\SetValueTrait;
use ProcessWire\GraphQL\Utils;
use ProcessWire\Page;

class FieldtypeFile
{
  use InputFieldTrait;
  use SetValueTrait;

  public static function type(Context $context, ?Field $field = null): ListOfType
  {
    return Type::listOf(FileType::type($context));
  }

  public static function field(Context $context, Field $field): array
  {
    return $context->types->field($field->name, fn() => [
      'name' => $field->name,
      'description' => Utils::fieldDescription($field),
      'type' => static::type($context, $field),
      'resolve' => static::batchResolver($field),
    ]);
  }

  /**
   * Collects the pages of one query level and loads the file rows of all of
   * them with a single query when the first Deferred runs.
   */
  public static function batchResolver(Field $field): \Closure
  {
    return function (Page $page, array $args, Context $context) use ($field) {
      $context->buffer->add($field->name, $page);
      return new Deferred(function () use ($page, $field, $context) {
        $ids = $context->buffer->get($field->name);
        $context->buffer->clear($field->name);
        if ($ids) {
          self::loadFilesData($context, $ids, $field);
        }
        return self::getFieldValue($context, $page, $field);
      });
    };
  }

  /**
   * Loads the raw file rows of the given pages into the context.
   *
   * `$pages->findRaw()` returns every column of the field table (including
   * the ones added by FieldtypeImage or custom file fields) in `sort` order,
   * with a single query for all pages.
   *
   * @param int[] $pageIDs
   */
  public static function loadFilesData(Context $context, array $pageIDs, Field $field): void
  {
    $pageIDs = array_values(array_unique(array_map('intval', $pageIDs)));
    if (empty($pageIDs)) {
      return;
    }

    $rows = $context->pages()->findRaw($pageIDs, $field->name);

    // pages without files come back as empty (non array) values
    $context->files[$field->name] = array_filter($rows, fn($value) => is_array($value) && $value);
  }

  public static function getFieldValue(Context $context, Page $page, Field $field)
  {
    $rows = $context->files[$field->name][$page->id] ?? null;
    if ($rows === null) {
      return [];
    }
    $value = $field->type->wakeupValue($page, $field, $rows);
    return $field->type->sanitizeValue($page, $field, $value);
  }
}

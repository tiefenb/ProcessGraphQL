<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\Type;
use ProcessWire\DatabaseQuerySelect;
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
   * @param int[] $pageIDs
   */
  public static function loadFilesData(Context $context, array $pageIDs, Field $field): void
  {
    if (empty($pageIDs)) {
      return;
    }

    $database = $context->database();
    $fieldType = $field->type;
    $fieldName = $database->escapeCol($field->name);
    $schema = $fieldType->getDatabaseSchema($field);
    $table = $database->escapeTable($field->table);
    $stmt = null;

    $query = $fieldType->wire(new DatabaseQuerySelect());
    $query = $fieldType->getLoadQuery($field, $query);
    $query->select("$table.pages_id AS `{$fieldName}__pages_id`"); // QA
    $ids = implode(', ', array_map('intval', $pageIDs));
    $query->where("$table.pages_id IN ($ids)");
    $query->from($table);

    try {
      $stmt = $query->prepare();
      $result = $database->execute($stmt);
    } catch (\Exception $e) {
      $result = false;
      $fieldType->trackException($e, false, true);
    }

    if (!$result) {
      return;
    }

    unset($schema['keys'], $schema['xtra'], $schema['sort']);
    $values = [];

    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
      $value = [];
      foreach ($schema as $k => $unused) {
        $value[$k] = $row["{$fieldName}__{$k}"];
      }
      $pageID = $value['pages_id'];
      unset($value['pages_id']);
      $values[$pageID][] = $value;
    }

    $stmt->closeCursor();

    $context->files[$field->name] = $values;
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

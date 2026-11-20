<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Inputfield\InputfieldRepeater;
use ProcessWire\GraphQL\Type\PageArrayType;
use ProcessWire\GraphQL\Type\SelectorType;
use ProcessWire\GraphQL\Utils;
use ProcessWire\NullPage;
use ProcessWire\Page;
use ProcessWire\PageArray;
use ProcessWire\RepeaterPage;
use ProcessWire\Selectors;

class FieldtypeRepeater
{
  public const NAME = 'Repeater';

  public const DESCRIPTION = 'Maintains a collection of fields that are repeated for any number of times.';

  private const BUFFER_PREFIX = 'repeater:';

  public static function type(Context $context, ?Field $field = null): ObjectType
  {
    $template = $context->templates()->get($field->get('template_id'));
    return PageArrayType::type($context, $template);
  }

  public static function field(Context $context, Field $field): array
  {
    return $context->types->field($field->name, fn() => [
      'name' => $field->name,
      'description' => Utils::fieldDescription($field),
      'type' => self::type($context, $field),
      'args' => [
        's' => [
          'type' => SelectorType::type($context),
          'description' => "ProcessWire selector."
        ],
      ],
      'resolve' => function (Page $page, array $args, Context $context, ResolveInfo $info) use ($field) {
        $group = self::BUFFER_PREFIX . $field->name;
        $context->buffer->add($group, $page);

        return new Deferred(function () use ($page, $args, $context, $info, $field, $group) {
          $ids = $context->buffer->get($group);
          $context->buffer->clear($group);
          if ($ids) {
            self::loadItems($context, $ids, $field, PageArrayType::getFinderOptions($context, $info)['loadOptions']);
          }

          $selectors = $context->wire->wire(new Selectors());
          $selectors->init($args['s'] ?? "");
          // filters the already loaded (formatted) repeater items in memory
          $result = self::getFieldValue($context, $page, $field)->find((string) $selectors);
          return $result instanceof NullPage ? null : $result;
        });
      }
    ]);
  }

  /**
   * Loads the repeater items of all given pages into the context: one query
   * for the field rows and one find for the items of all pages, instead of
   * three queries per page.
   *
   * @param int[] $pageIDs
   * @param array $loadOptions Additional load options, e.g. the fields to join
   */
  public static function loadItems(Context $context, array $pageIDs, Field $field, array $loadOptions = []): void
  {
    $pageIDs = array_values(array_unique(array_map('intval', $pageIDs)));
    $templateID = (int) $field->get('template_id');
    if (empty($pageIDs) || !$templateID) {
      return;
    }

    $pages = $context->pages();
    $fieldtype = $field->type;

    // repeater parent of every page: [ page id => parent id ]
    $parentIDs = [];
    foreach ($pages->findRaw($pageIDs, "{$field->name}.parent_id") as $pageID => $parentID) {
      if (is_numeric($parentID) && (int) $parentID > 0) {
        $parentIDs[(int) $pageID] = (int) $parentID;
      }
    }

    $itemsByParent = [];
    if ($parentIDs) {
      // the same find as FieldtypeRepeater::wakeupValue(), for all parents at once
      $selector = 'parent_id=' . implode('|', array_unique($parentIDs)) . ", templates_id=$templateID, sort=sort, include=all";
      $items = $pages->find($selector, [
        'cache' => false,
        'getTotal' => false,
        'caller' => self::class . '::loadItems',
        'loadOptions' => [
          ...$loadOptions,
          'cache' => false,
          'template' => $context->templates()->get($templateID),
          'pageClass' => $fieldtype->getCustomPageClass($field),
        ],
      ]);
      foreach ($items as $item) {
        $itemsByParent[$item->parent_id][] = $item;
      }
    }

    foreach ($pageIDs as $pageID) {
      // pages without a row are left out, they are loaded by ProcessWire itself
      if (isset($parentIDs[$pageID])) {
        $context->repeaters[$field->name][$pageID] = $itemsByParent[$parentIDs[$pageID]] ?? [];
      }
    }
  }

  /**
   * The (formatted) repeater items of the page, from the batch loaded items
   * when available.
   */
  public static function getFieldValue(Context $context, Page $page, Field $field): PageArray
  {
    $fieldName = $field->name;
    $items = $context->repeaters[$fieldName][$page->id] ?? null;
    if ($items === null) {
      $value = $page->$fieldName;
      return $value instanceof PageArray ? $value : $field->type->getBlankValue($page, $field);
    }

    // mirrors FieldtypeRepeater::wakeupValue()
    $fieldtype = $field->type;
    $value = $fieldtype->getBlankValue($page, $field);
    foreach ($items as $item) {
      if ($item instanceof RepeaterPage) {
        $item->setForPage($page);
      }
      $value->add($item);
    }
    $value->resetTrackChanges(true);

    return $page->of() ? $fieldtype->formatValue($page, $field, $value) : $value;
  }

  public static function inputField(Context $context, Field $field): array
  {
    return InputfieldRepeater::inputField($context, $field);
  }

  public static function setValue(Context $context, Page $page, Field $field, $value): void
  {
    InputfieldRepeater::setValue($context, $page, $field, $value);
  }
}

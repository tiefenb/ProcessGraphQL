<?php

namespace ProcessWire\GraphQL;

use ProcessWire\GraphQL\Utils;
use ProcessWire\GraphQL\Type\SelectorType;
use ProcessWire\Page;

class PagesBuffer
{
  private static array $pageIDs = [];

  public static function loadPages($group, $options)
  {
    if (!isset(self::$pageIDs[$group]) || empty(self::$pageIDs[$group])) {
      // nothing to load if there are no page ids
      return;
    }
    $ids = array_unique(self::$pageIDs[$group]);
    $selector = SelectorType::parseValue("", count($ids));
    $selector .= ', id=' . implode('|', $ids);
    // the found pages only warm the page cache, no total is needed
    Utils::pages()->find($selector, [...$options, 'getTotal' => false]);
    self::clear($group);
  }

  /**
   * Loads the buffered page IDs of the group into ProcessWire's page cache in
   * one query, so following $pages->get($id) calls are served from memory.
   * Access control is not applied here, callers have to check it themselves.
   */
  public static function preload(string $group): void
  {
    $ids = array_values(array_unique(array_filter(self::$pageIDs[$group] ?? [])));
    self::clear($group);
    if (!$ids) {
      return;
    }
    $pages = Utils::pages();
    $ids = array_values(array_filter($ids, fn($id) => !$pages->getCache($id)));
    if ($ids) {
      $pages->getByIDs($ids);
    }
  }

  public static function clear($group)
  {
    self::$pageIDs[$group] = [];
  }

  public static function add($group, $_ids)
  {
    if(is_string($_ids)) {
      // convert string of IDs to array
			if(str_contains($_ids, '|')) $_ids = explode('|', $_ids);
			else $_ids = explode(",", $_ids);
		} else if(is_int($_ids) || is_object($_ids)) {
			$_ids = array($_ids);
		}

    // sanitize ids
    $ids = [];
    foreach ($_ids as $id) {
      if (is_string($id)) {
        $id = (int) $id;
      } else if (is_object($id)) {
        $id = $id->id;
      }
      $ids[] = (int) $id;
    }

    if (!isset(self::$pageIDs[$group])) {
      self::$pageIDs[$group] = [];
    }
    array_push(self::$pageIDs[$group], ...$ids);
    return $ids;
  }

  public static function get($group)
  {
    return self::$pageIDs[$group] ?? [];
  }
}

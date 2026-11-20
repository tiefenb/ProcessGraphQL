<?php

namespace ProcessWire\GraphQL;

use ProcessWire\GraphQL\Type\SelectorType;
use ProcessWire\WireArray;

/**
 * Collects page ids of one query level, so that they can be loaded with a
 * single query from a GraphQL\Deferred (avoids N+1 queries).
 */
final class PagesBuffer
{
  /** @var array<string, int[]> */
  private array $pageIDs = [];

  public function __construct(private readonly Context $context)
  {
  }

  /**
   * Loads the buffered pages of the group through the client selector rules
   * (legal and viewable templates only) into ProcessWire's page cache.
   */
  public function loadPages(string $group, array $options = []): void
  {
    $ids = array_unique($this->pageIDs[$group] ?? []);
    $this->clear($group);
    if (!$ids) {
      return;
    }
    $selector = SelectorType::parseValue($this->context, "", count($ids));
    $selector .= ', id=' . implode('|', $ids);
    // the found pages only warm the page cache, no total is needed
    $this->context->pages()->find($selector, [...$options, 'getTotal' => false]);
  }

  /**
   * Loads the buffered page IDs of the group into ProcessWire's page cache in
   * one query, so following $pages->get($id) calls are served from memory.
   * Access control is not applied here, callers have to check it themselves.
   */
  public function preload(string $group): void
  {
    $ids = array_values(array_unique(array_filter($this->pageIDs[$group] ?? [])));
    $this->clear($group);
    if (!$ids) {
      return;
    }
    $pages = $this->context->pages();
    $ids = array_values(array_filter($ids, fn($id) => !$pages->getCache($id)));
    if ($ids) {
      $pages->getByIDs($ids);
    }
  }

  /**
   * Loads the ancestors that ProcessWire needs to find the access template of
   * the given (already loaded) pages, one query level at a time.
   *
   * A page whose template does not define access inherits it from its closest
   * ancestor that does. ProcessWire loads those ancestors one by one, e.g. to
   * decide if the files of a page are secured, which is one load per parent.
   *
   * @param int[] $pageIDs
   */
  public function preloadAccessParents(array $pageIDs): void
  {
    $pages = $this->context->pages();
    $seen = [];

    // the depth of the page tree is the upper bound, 20 is a safety net
    for ($level = 0; $level < 20 && $pageIDs; $level++) {
      $parentIDs = [];
      foreach ($pageIDs as $id) {
        $page = $pages->getCache((int) $id);
        if (!$page || $page->template?->useRoles) {
          continue;
        }
        $parentID = (int) $page->parent_id;
        if ($parentID > 0 && !isset($seen[$parentID])) {
          $seen[$parentID] = true;
          $parentIDs[] = $parentID;
        }
      }

      $missing = array_values(array_filter($parentIDs, fn($id) => !$pages->getCache($id)));
      if ($missing) {
        $pages->getByIDs($missing);
      }
      $pageIDs = $parentIDs;
    }
  }

  public function clear(string $group): void
  {
    $this->pageIDs[$group] = [];
  }

  /**
   * Adds page ids to the group.
   *
   * @param int|string|object|array<int|string|object> $_ids Id(s), "1|2|3", "1,2,3" or Page(s)
   * @return int[] The sanitized ids that were added
   */
  public function add(string $group, mixed $_ids): array
  {
    if (is_string($_ids)) {
      $_ids = explode(str_contains($_ids, '|') ? '|' : ',', $_ids);
    } elseif ($_ids instanceof WireArray) {
      $_ids = $_ids->explode('id');
    } elseif (is_int($_ids) || is_object($_ids)) {
      $_ids = [$_ids];
    }

    $ids = [];
    foreach ($_ids as $id) {
      $ids[] = is_object($id) ? (int) $id->id : (int) $id;
    }

    $this->pageIDs[$group] ??= [];
    array_push($this->pageIDs[$group], ...$ids);
    return $ids;
  }

  /** @return int[] */
  public function get(string $group): array
  {
    return $this->pageIDs[$group] ?? [];
  }
}

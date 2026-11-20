<?php namespace ProcessWire\GraphQL\Type;

use GraphQL\Error\Error;
use ProcessWire\Template;
use ProcessWire\Selectors;
use ProcessWire\Selector;
use ProcessWire\SelectorEqual;
use ProcessWire\GraphQL\Utils;
use ProcessWire\GraphQL\Cache;
use GraphQL\Type\Definition\CustomScalarType;
use ProcessWire\GraphQL\Permissions;

class SelectorType
{
  public static string $name = 'Selector';

  public static string $description = 'ProcessWire selector.';

  private static array $parsedValues = [];

  public static function type()
  {
    return Cache::type(self::$name, function () {
      return new CustomScalarType([
        'name' => self::$name,
        'description' => self::$description,
        'serialize' => function ($value) {
          return (string) $value;
        },
        'parseValue' => function ($value) {
          return (string) $value;
        },
        'parseLiteral' => function ($valueNode) {
          return (string) $valueNode->value;
        }
      ]);
    });
  }

  /**
   * Parses an externally provided value (query variable) to use as an input
   *
   * @param mixed $value
   * @return string
   * @throws Error
   */
  public static function parseValue(string $value, ?int $maxLimit = null)
  {
    $maxLimit ??= (int) Utils::module()->maxLimit;

    // if we already parsed the given selector
    // then return the cached result.
    $key = self::getCacheKeyForSelector($value, $maxLimit);
    if (isset(self::$parsedValues[$key])) {
      return self::$parsedValues[$key];
    }


    if (!is_string($value)) {
      throw new Error(self::$name . " should be a string.");
    }

    $selectors = new Selectors($value);

    self::assertAllowedFields($selectors);

    // make sure the limit field is not greater than max allowed
    $limitSelector = $selectors->getSelectorByField('limit', true);
    if ($limitSelector instanceof Selector) {
      if ($maxLimit < $limitSelector->value) $limitSelector->set('value', $maxLimit);
    } else {
      $limitSelector = new SelectorEqual('limit', $maxLimit);
      $selectors->add($limitSelector);
    }

    // make sure to limit the search to legal templates only
    $templateSelector = $selectors->getSelectorByField('template', true);
    $legalTemplates = Permissions::getViewTemplates();
    $names = [];
    
    if ($templateSelector instanceof Selector) {
      // the user did specify `template` field in selector string
      // filter out templates that are not legal
      foreach ($templateSelector->values as $templateName) {
        $template = $legalTemplates->get($templateName);
        if ($template instanceof Template) $names[] = $template->name;
      }

      if (!empty($names)) {
        // at least one of the templates the user chose is legal
        // let the user search those
        $templateSelector->set('value', $names);
      } else {
        // none of the templates the user chose is viewable by her.
        // This means the result of the search should be empty PageArrayType;
        $selectors = new Selectors("name=''");
      }

    } else {
      // The user did not specify the template field in selector string.
      // That means she wants to see all the matching pages, but we add
      // template field and set all the legal templates to narrow the
      // search only to viewable pages
      $templateSelector = new SelectorEqual('template', $legalTemplates->explode('name'));
      $selectors->add($templateSelector);
    }

    // return selector as string
    self::$parsedValues[$key] = (string) $selectors;
    return self::$parsedValues[$key];
  }

  /**
   * Returns the selector fields the client is not allowed to use.
   *
   * @return string[]
   */
  public static function getForbiddenFields(): array
  {
    // always forbidden selectors
    $forbidden = ['force_access', 'instance_id'];

    // selectors that change which pages are visible: status/include/access.
    // Only allowed if explicitly enabled via module settings.
    if (!Utils::module()->allowAdvancedSelectors) {
      $forbidden = [
        ...$forbidden,
        'include',
        'status',
        'status_id',
        'check_access',
        'checkAccess',
      ];
    }

    return $forbidden;
  }

  /**
   * Throws if the selectors (including nested sub-selectors like
   * `field=[...]` and OR-groups `(...)`) use a forbidden field.
   *
   * @throws Error
   */
  public static function assertAllowedFields(Selectors $selectors, ?array $forbidden = null): void
  {
    $forbidden ??= array_flip(self::getForbiddenFields());
    foreach ($selectors as $selector) {
      foreach ($selector->fields as $field) {
        if (isset($forbidden[$field])) {
          throw new Error("The selector field '$field' is not allowed.");
        }
      }

      // nested selectors: field=[sub selector] and (or, groups)
      if ($selector->quote === '[' || $selector->quote === '(') {
        foreach ($selector->values as $nested) {
          if (is_string($nested) && str_contains($nested, '=')) {
            self::assertAllowedFields(new Selectors($nested), $forbidden);
          }
        }
      }
    }
  }

  /**
   * @deprecated Use ProcessWire's `$selectors->getSelectorByField($field, true)`.
   */
  public static function findSelectorByField(Selectors $selectors, $target)
  {
    return $selectors->getSelectorByField($target, true);
  }

  public static function getCacheKeyForSelector(string $selector, ?int $maxLimit = null)
  {
    $key = $selector;
    $seperator = '-';

    // max limit
    $key .= "$seperator$seperator";
    $key .= $maxLimit ?? Utils::module()->maxLimit;

    // legal templates
    $key .= "$seperator$seperator";
    $key .= Permissions::getViewTemplates()->implode('-', 'name');

    return $key;
  }
}
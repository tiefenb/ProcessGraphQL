<?php namespace ProcessWire\GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\CustomScalarType;
use ProcessWire\GraphQL\Context;
use ProcessWire\Selector;
use ProcessWire\SelectorEqual;
use ProcessWire\Selectors;
use ProcessWire\Template;

class SelectorType
{
  public const NAME = 'Selector';

  public const DESCRIPTION = 'ProcessWire selector.';

  public static function type(Context $context): CustomScalarType
  {
    return $context->types->type(self::NAME, fn() => new CustomScalarType([
      'name' => self::NAME,
      'description' => self::DESCRIPTION,
      'serialize' => fn($value) => (string) $value,
      'parseValue' => fn($value) => (string) $value,
      'parseLiteral' => fn($valueNode) => (string) $valueNode->value,
    ]));
  }

  /**
   * Turns a client provided selector into a safe selector string:
   * forbidden fields are rejected, `limit` is capped to $maxLimit (module
   * setting by default) and the search is restricted to legal templates the
   * current user can view.
   *
   * @throws Error
   */
  public static function parseValue(Context $context, string $value, ?int $maxLimit = null): string
  {
    $maxLimit ??= (int) $context->module->maxLimit;
    $legalTemplates = $context->permissions->getViewTemplates();

    // the result depends on the selector, the limit and the viewable templates
    $key = implode('--', [$value, $maxLimit, $legalTemplates->implode('-', 'name')]);
    if (isset($context->selectors[$key])) {
      return $context->selectors[$key];
    }

    $selectors = self::newSelectors($context, $value);

    self::assertAllowedFields($context, $selectors);

    // make sure the limit field is not greater than max allowed
    $limitSelector = $selectors->getSelectorByField('limit', true);
    if ($limitSelector instanceof Selector) {
      if ($maxLimit < $limitSelector->value) {
        $limitSelector->set('value', $maxLimit);
      }
    } else {
      $selectors->add(new SelectorEqual('limit', $maxLimit));
    }

    // make sure to limit the search to legal templates only
    $templateSelector = $selectors->getSelectorByField('template', true);

    if ($templateSelector instanceof Selector) {
      // the client specified `template`: keep only the legal ones
      $names = [];
      foreach ($templateSelector->values as $templateName) {
        $template = $legalTemplates->get($templateName);
        if ($template instanceof Template) {
          $names[] = $template->name;
        }
      }

      if ($names) {
        $templateSelector->set('value', $names);
      } else {
        // none of the requested templates is viewable: match nothing
        $selectors = self::newSelectors($context, "name=''");
      }
    } else {
      // no template given: narrow the search to the viewable templates
      $selectors->add(new SelectorEqual('template', $legalTemplates->explode('name')));
    }

    return $context->selectors[$key] = (string) $selectors;
  }

  /**
   * Returns the selector fields the client is not allowed to use.
   *
   * @return string[]
   */
  public static function getForbiddenFields(Context $context): array
  {
    // always forbidden selectors
    $forbidden = ['force_access', 'instance_id'];

    // selectors that change which pages are visible: status/include/access.
    // Only allowed if explicitly enabled via module settings.
    if (!$context->module->allowAdvancedSelectors) {
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
  public static function assertAllowedFields(Context $context, Selectors $selectors, ?array $forbidden = null): void
  {
    $forbidden ??= array_flip(self::getForbiddenFields($context));
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
            self::assertAllowedFields($context, self::newSelectors($context, $nested), $forbidden);
          }
        }
      }
    }
  }

  /**
   * Creates Selectors bound to the ProcessWire instance of the context.
   */
  private static function newSelectors(Context $context, string $selector): Selectors
  {
    $selectors = $context->wire->wire(new Selectors());
    $selectors->init($selector);
    return $selectors;
  }
}

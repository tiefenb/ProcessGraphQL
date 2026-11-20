<?php namespace ProcessWire\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use ProcessWire\Field;
use ProcessWire\FieldtypeFile as PWFieldtypeFile;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Utils;
use ProcessWire\Template;

class PageArrayType
{
  /**
   * The generic `PageArray` type, or the one of the given template.
   */
  public static function type(Context $context, ?Template $template = null): ObjectType
  {
    $name = self::getName($template);
    return $context->types->type($name, fn() => new ObjectType([
      'name' => $name,
      'description' => self::getDescription($template),
      'fields' => fn() => [
        ...self::getPaginationFields($context),
        [
          'name' => 'list',
          'type' => Type::listOf(PageType::type($context, $template)),
          'description' => $template ? "List of $name" : 'List of PW Pages.',
          'resolve' => fn($value) => $value,
        ],
        [
          'name' => 'first',
          'type' => PageType::type($context, $template),
          'description' => 'Returns the first item in the WireArray.',
          'resolve' => fn($value) => $value->first() ?: null,
        ],
        [
          'name' => 'last',
          'type' => PageType::type($context, $template),
          'description' => 'Returns the last item in the WireArray.',
          'resolve' => fn($value) => $value->last() ?: null,
        ],
      ],
    ]));
  }

  public static function getName(?Template $template = null): string
  {
    if ($template instanceof Template) {
      return Utils::normalizeTypeName("{$template->name}PageArray");
    }

    return 'PageArray';
  }

  public static function getDescription(?Template $template = null): string
  {
    if ($template instanceof Template) {
      return $template->description ?: "PageArray with template `{$template->name}`.";
    }
    return 'ProcessWire PageArray.';
  }

  /**
   * The query field that finds pages of the given template.
   */
  public static function field(Context $context, Template $template): array
  {
    return [
      'name' => Utils::normalizeFieldName($template->name),
      'description' => self::getDescription($template),
      'type' => self::type($context, $template),
      'args' => [
        's' => [
          'type' => SelectorType::type($context),
          'description' => "ProcessWire selector."
        ],
      ],
      'resolve' => function ($root, array $args, Context $context, ResolveInfo $info) use ($template) {
        $selector = "template=$template";
        if (isset($args['s'])) {
          $selector .= ", " . $args['s'];
        }
        return $context->pages()->find(
          SelectorType::parseValue($context, $selector),
          self::getFinderOptions($context, $info)
        );
      }
    ];
  }

  public static function getFinderOptions(Context $context, ResolveInfo $info): array
  {
    $loadOptions = [];
    $subfields = $info->getFieldSelection(1);
    if (isset($subfields['list']) && is_array($subfields['list'])) {
      $joinFields = self::getJoinFields($context, array_keys($subfields['list']));
      if ($joinFields) {
        $loadOptions['autojoin'] = true;
        $loadOptions['joinFields'] = $joinFields;
      }
    }
    return [
      'loadOptions' => $loadOptions,
      // counting the total (SQL_CALC_FOUND_ROWS / COUNT) is only
      // needed if the client actually asks for it
      'getTotal' => isset($subfields['getTotal']),
    ];
  }

  /**
   * Filters the requested subfield names down to the fields that are worth
   * autojoining. File and image fields are loaded in a batch by
   * FieldtypeFile::loadFilesData(), so joining them would load them twice.
   *
   * @param string[] $names
   * @return string[]
   */
  public static function getJoinFields(Context $context, array $names): array
  {
    $fields = $context->fields();
    return array_values(array_filter($names, function ($name) use ($fields) {
      $field = $fields->get($name);
      return $field instanceof Field && !$field->type instanceof PWFieldtypeFile;
    }));
  }

  public static function getPaginationFields(Context $context): array
  {
    $maxLimit = $context->module->maxLimit;
    return [
      [
        'name' => 'getTotal',
        'type' => Type::int(),
        'description' => 'Get the total number of pages that were found from a $pages->find("selectors, limit=n")
                          operation that led to this PageArray. The number returned may be greater than the number
                          of pages actually in PageArray, and is used for calculating pagination.
                          Whereas `count` will always return the number of pages actually in PageArray.',
        'resolve' => fn($value) => (int) $value->getTotal(),
      ],
      [
        'name' => 'getLimit',
        'type' => Type::int(),
        'description' => "Get the number (n) from a 'limit=n' portion of a selector that resulted in the PageArray.
                          In pagination, this value represents the max items to display per page. The default limit
                          is set to $maxLimit.",
        'resolve' => fn($value) => (int) $value->getLimit(),
      ],
      [
        'name' => 'getStart',
        'type' => Type::int(),
        'description' => "Get the number of the starting result that led to the PageArray in pagination.
                          Returns 0 if in the first page of results.",
        'resolve' => fn($value) => (int) $value->getStart(),
      ],
    ];
  }
}

<?php namespace ProcessWire\GraphQL\Type;

use GraphQL\Deferred;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Utils;
use ProcessWire\NullPage;
use ProcessWire\Page;
use ProcessWire\Template;
use ProcessWire\WireData;

class PageType
{
  private const PARENT_BUFFER = '__pageParent';

  private const USER_BUFFER = '__pageUser';

  /**
   * The `Page` interface, or the object type of the given template.
   */
  public static function type(Context $context, ?Template $template = null): InterfaceType|ObjectType
  {
    if ($template instanceof Template) {
      return self::templateType($context, $template);
    }

    return $context->types->type(self::getName(), fn() => new InterfaceType([
      'name' => self::getName(),
      'description' => self::getDescription(),
      'fields' => fn() => self::getLegalBuiltInFields($context),
      'resolveType' => fn($value) => $value->id && $value->template instanceof Template
        ? self::templateType($context, $value->template)
        : null,
    ]));
  }

  public static function templateType(Context $context, Template $template): ObjectType
  {
    return $context->types->type(self::getName($template), fn() => new ObjectType([
      'name' => self::getName($template),
      'description' => self::getDescription($template),
      'fields' => fn() => self::getFields($context, $template),
      'interfaces' => fn() => [self::type($context)],
    ]));
  }

  public static function getBuiltInFields(Context $context): array
  {
    $type = self::type($context);
    return [
      self::resolvePagefieldWithSelector($context, [
        'name' => 'child',
        'type' => $type,
        'description' => "The first child of this page. If the `s`(selector) argument is provided then the
                          first matching child (subpage) that matches the given selector. Returns a Page or null.",
      ]),

      self::resolvePagefieldWithSelector($context, [
        'name' => 'children',
        'type' => PageArrayType::type($context),
        'description' => "The number of children (subpages) this page has, optionally limiting to visible
                          pages. When argument `visible` true, number includes only visible children
                          (excludes unpublished, hidden, no-access, etc.)",
      ]),

      self::resolveWithDateFormatter([
        'name' => 'created',
        'type' => Type::nonNull(Type::string()),
        'description' => 'Date of when the page was created.',
      ]),

      self::resolveUser([
        'name' => 'createdUser',
        'type' => Type::nonNull(UserType::type($context)),
        'description' => 'The user that created this page.',
      ]),

      [
        'name' => 'httpUrl',
        'type' => Type::nonNull(Type::string()),
        'description' => 'Same as `url`, except includes protocol (http or https) and hostname.',
      ],

      [
        'name' => 'id',
        'type' => Type::nonNull(Type::id()),
        'description' => 'ProcessWire Page id.',
      ],

      self::resolveWithDateFormatter([
        'name' => 'modified',
        'type' => Type::nonNull(Type::string()),
        'description' => 'Date of when the page was last modified.',
      ]),

      self::resolveUser([
        'name' => 'modifiedUser',
        'type' => Type::nonNull(UserType::type($context)),
        'description' => 'The user that last modified this page.',
      ]),

      [
        'name' => 'name',
        'type' => Type::nonNull(Type::string()),
        'description' => 'ProcessWire Page name.',
      ],

      [
        'name' => 'numChildren',
        'type' => Type::nonNull(Type::int()),
        'description' => "The number of children (subpages) this page has, optionally limiting to
                          visible pages. When argument `visible` true, number includes only visible
                          children (excludes unpublished, hidden, no-access, etc.)",
        'args' => [
          'visible' => [
            'type' => Type::boolean(),
          ],
        ],
        'resolve' => fn(Page $page, array $args) => !empty($args['visible'])
          ? $page->numChildren(true)
          : $page->numChildren,
      ],

      self::resolvePagefieldWithSelector($context, [
        'name' => 'parent',
        'type' => $type,
        'description' => 'The parent Page object, or the closest parent matching the given selector. Returns `null` if there is no parent or no match.'
      ]),

      [
        'name' => 'parentID',
        'type' => Type::nonNull(Type::id()),
        'description' => 'The numbered ID of the parent page or 0 if none.',
      ],

      self::resolvePagefieldWithSelector($context, [
        'name' => 'parents',
        'type' => Type::nonNull(PageArrayType::type($context)),
        'description' => "Return this page's parent pages as PageArray. Optionally filtered by a selector.",
      ]),

      [
        'name' => 'path',
        'type' => Type::nonNull(Type::string()),
        'description' => "The page's URL path from the homepage (i.e. /about/staff/ryan/)",
      ],

      self::resolveReferencesWithSelector($context, [
        'name' => 'references',
        'type' => Type::nonNull(PageArrayType::type($context)),
        'description' => "Return this page's parent pages as PageArray. Optionally filtered by a selector and a field name.",
      ]),

      [
        'name' => 'template',
        'type' => Type::nonNull(Type::string()),
        'description' => 'Template name of the page.',
      ],

      [
        'name' => 'url',
        'type' => Type::nonNull(Type::string()),
        'description' => "The page's URL path from the server's document root (may be the same as the `path`)",
      ],
    ];
  }

  public static function getLegalBuiltInFields(Context $context): array
  {
    $legal = array_flip($context->module->legalPageFields);
    return array_filter(
      self::getBuiltInFields($context),
      fn($field) => isset($legal[$field['name']])
    );
  }

  public static function getFields(Context $context, Template $template): array
  {
    $fields = [];

    // add the template fields
    $legalFields = array_flip($context->module->legalFields);
    foreach ($template->fields as $field) {
      // skip illegal fields
      if (!isset($legalFields[$field->name])) {
        continue;
      }

      // check if user has permission to view this field
      if (!$context->permissions->canViewField($field, $template)) {
        continue;
      }

      $fieldClass = Utils::pwFieldToGraphqlClass($field);
      if ($fieldClass === null) {
        continue;
      }

      $fieldSettings = $fieldClass::field($context, $field);
      if ($field->required) {
        $fieldSettings['type'] = Type::nonNull($fieldSettings['type']);
      }
      $fields[] = $fieldSettings;
    }

    // add all the built in page fields
    foreach (self::type($context)->getFields() as $field) {
      $fields[] = $field;
    }

    return $fields;
  }

  public static function getName(?Template $template = null): string
  {
    if ($template instanceof Template) {
      return Utils::normalizeTypeName("{$template->name}Page");
    }

    return 'Page';
  }

  public static function getDescription(?Template $template = null): string
  {
    if ($template instanceof Template) {
      return $template->description ?: "PageType with template `{$template->name}`.";
    }
    return 'ProcessWire Page.';
  }

  public static function resolvePagefieldWithSelector(Context $context, array $options): array
  {
    return [...$options,
      'args' => [
        's' => [
          'type' => SelectorType::type($context),
          'description' => "ProcessWire selector."
        ],
      ],
      'resolve' => function (Page $page, array $args, Context $context) use ($options) {
        $name = $options['name'];
        $selector = SelectorType::parseValue($context, $args['s'] ?? "");
        $resolve = function () use ($page, $name, $selector) {
          $result = $page->$name($selector);
          return $result instanceof NullPage ? null : $result;
        };

        // parents of all pages on this level are loaded with one query
        if ($name === 'parent' && $page->parent_id) {
          $context->buffer->add(self::PARENT_BUFFER, (int) $page->parent_id);
          return new Deferred(function () use ($context, $resolve) {
            $context->buffer->preload(self::PARENT_BUFFER);
            return $resolve();
          });
        }

        return $resolve();
      }
    ];
  }

  public static function resolveReferencesWithSelector(Context $context, array $options): array
  {
    return [...$options,
      'args' => [
        's' => [
          'type' => SelectorType::type($context),
          'description' => "ProcessWire selector."
        ],
        'f' => [
          'type' => Type::string(), // TODO: could also be of type Field or bool
          'description' => "ProcessWire field name."
        ]
      ],
      'resolve' => function (Page $page, array $args, Context $context) use ($options) {
        $name = $options['name'];
        $selector = SelectorType::parseValue($context, $args['s'] ?? "");
        $result = $page->$name($selector, $args['f'] ?? "");
        return $result instanceof NullPage ? null : $result;
      }
    ];
  }

  /**
   * Placeholder for users that are not viewable by the current user.
   */
  public static function getEmptyUser(): WireData
  {
    $value = new WireData();
    $value->name = '';
    $value->email = '';
    $value->id = '';
    return $value;
  }

  public static function resolveUser(array $options): array
  {
    return [...$options,
      'resolve' => function (Page $page, array $args, Context $context) use ($options) {
        $name = $options['name'];
        $idProperty = $name === 'createdUser' ? 'created_users_id' : 'modified_users_id';
        $context->buffer->add(self::USER_BUFFER, (int) $page->$idProperty);

        // users of all pages on this level are loaded with one query
        return new Deferred(function () use ($page, $name, $context) {
          $context->buffer->preload(self::USER_BUFFER);
          $result = $page->$name;
          if ($result instanceof NullPage) {
            return self::getEmptyUser();
          }
          if ($result instanceof Page) {
            return $context->permissions->getViewTemplates()->has($result->template)
              ? $result
              : self::getEmptyUser();
          }
          return $result;
        });
      }
    ];
  }

  public static function resolveWithDateFormatter(array $options): array
  {
    return [...$options,
      'args' => [
        'format' => [
          'type' => Type::string(),
          'description' => "PHP date formatting string. Refer to https://devdocs.io/php/function.date",
        ],
      ],
      'resolve' => function (Page $page, array $args) use ($options) {
        $name = $options['name'];

        if (isset($args['format'])) {
          $rawValue = $page->$name;
          return $rawValue ? date($args['format'], $rawValue) : "";
        }

        return $page->$name;
      }
    ];
  }
}

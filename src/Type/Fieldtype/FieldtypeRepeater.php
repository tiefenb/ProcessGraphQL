<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Type\Definition\ObjectType;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Inputfield\InputfieldRepeater;
use ProcessWire\GraphQL\Type\PageArrayType;
use ProcessWire\GraphQL\Type\SelectorType;
use ProcessWire\GraphQL\Utils;
use ProcessWire\NullPage;
use ProcessWire\Page;
use ProcessWire\Selectors;

class FieldtypeRepeater
{
  public const NAME = 'Repeater';

  public const DESCRIPTION = 'Maintains a collection of fields that are repeated for any number of times.';

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
      'resolve' => function (Page $page, array $args, Context $context) use ($field) {
        $fieldName = $field->name;
        $selectors = $context->wire->wire(new Selectors());
        $selectors->init($args['s'] ?? "");
        // filters the already loaded (formatted) repeater items in memory
        $result = $page->$fieldName->find((string) $selectors);
        return $result instanceof NullPage ? null : $result;
      }
    ]);
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

<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use ProcessWire\Field;
use ProcessWire\FieldtypePage as PWFieldtypePage;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Type\Inputfield\InputfieldPage;
use ProcessWire\GraphQL\Type\PageArrayType;
use ProcessWire\GraphQL\Type\SelectorType;
use ProcessWire\GraphQL\Utils;
use ProcessWire\Page;
use ProcessWire\PageArray;
use ProcessWire\Template;

class FieldtypePage
{
  public static function type(Context $context, ?Field $field = null): ObjectType
  {
    // if a template is chosen for the FieldtypePage
    // then we resolve to the templated PageArray type
    $template = $field?->template_id
      ? $context->templates()->get($field->template_id)
      : null;

    return PageArrayType::type($context, $template instanceof Template ? $template : null);
  }

  public static function field(Context $context, Field $field): array
  {
    return $context->types->field($field->name, fn() => [
      'name' => $field->name,
      'description' => Utils::fieldDescription($field),
      'type' => self::type($context, $field),
      'resolve' => function (Page $value, array $args, Context $context, ResolveInfo $info) use ($field) {
        $data = $value->getArray();
        if (!empty($data[$field->name])) {
          $pageIDs = $context->buffer->add($field->name, $data[$field->name]);
          if (count($pageIDs)) {
            $value->data($field->name, $pageIDs);
          }
        }

        return new Deferred(function () use ($value, $field, $context, $info) {
          $context->buffer->loadPages($field->name, PageArrayType::getFinderOptions($context, $info));

          // temporarily read the value as PageArray, without changing the
          // field setting for the rest of the request
          $fieldName = $field->name;
          $derefAsPage = $field->derefAsPage;
          $field->derefAsPage = PWFieldtypePage::derefAsPageArray;
          try {
            $pages = $value->$fieldName;
          } finally {
            $field->derefAsPage = $derefAsPage;
          }

          if (!$pages instanceof PageArray) {
            return new PageArray();
          }
          return $pages->find(SelectorType::parseValue($context, ""));
        });
      }
    ]);
  }

  public static function inputField(Context $context, Field $field): array
  {
    return InputfieldPage::inputField($context, $field);
  }

  public static function setValue(Context $context, Page $page, Field $field, $value): void
  {
    InputfieldPage::setValue($context, $page, $field, $value);
  }
}

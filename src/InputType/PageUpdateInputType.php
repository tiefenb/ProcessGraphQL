<?php namespace ProcessWire\GraphQL\InputType;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Utils;
use ProcessWire\Page;
use ProcessWire\Template;

class PageUpdateInputType
{
  public static function getName(Template $template): string
  {
    return Utils::normalizeTypeName("{$template->name}UpdateInput");
  }

  public static function type(Context $context, Template $template): InputObjectType
  {
    return $context->types->type(self::getName($template), fn() => new InputObjectType([
      'name' => self::getName($template),
      'description' => "UpdateInputType for pages with template `{$template->name}`.",
      'fields' => fn() => self::getFields($context, $template),
    ]));
  }

  public static function getFields(Context $context, Template $template): array
  {
    return [
      [
        'name' => 'id',
        'type' => Type::nonNull(Type::id()),
        'description' => 'ProcessWire Page id of the page you want to update.',
      ],
      ...PageCreateInputType::getBuiltInFields(),
      ...PageCreateInputType::getTemplateFields($context, $template),
    ];
  }

  public static function setValues(Context $context, Page $page, array $values): void
  {
    PageCreateInputType::setValues($context, $page, $values);
  }
}

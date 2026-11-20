<?php namespace ProcessWire\GraphQL\InputType;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use ProcessWire\GraphQL\Context;
use ProcessWire\Template;

class RepeaterUpdateInputType
{
  public static function type(Context $context, Template $template): InputObjectType
  {
    $name = PageUpdateInputType::getName($template);
    return $context->types->type($name, fn() => new InputObjectType([
      'name' => $name,
      'description' => "UpdateInputType for pages with template `{$template->name}`.",
      'fields' => fn() => self::getFields($context, $template),
    ]));
  }

  public static function getFields(Context $context, Template $template): array
  {
    return [
      // the id of the repeater item that is being updated
      [
        'name' => 'id',
        'type' => Type::nonNull(Type::id()),
        'description' => 'The id of the repeater item that you want to update.',
      ],
      ...PageCreateInputType::getTemplateFields($context, $template),
    ];
  }
}

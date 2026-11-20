<?php namespace ProcessWire\GraphQL\InputType;

use GraphQL\Type\Definition\InputObjectType;
use ProcessWire\GraphQL\Context;
use ProcessWire\Template;

class RepeaterCreateInputType
{
  public static function type(Context $context, Template $template): InputObjectType
  {
    $name = PageCreateInputType::getName($template);
    return $context->types->type($name, fn() => new InputObjectType([
      'name' => $name,
      'description' => "CreateInputType for pages with template `{$template->name}`.",
      'fields' => fn() => self::getFields($context, $template),
    ]));
  }

  public static function getFields(Context $context, Template $template): array
  {
    // template fields only, with required fields marked as nonNull
    return PageCreateInputType::markRequiredTemplateFields(
      PageCreateInputType::getTemplateFields($context, $template),
      $template
    );
  }
}

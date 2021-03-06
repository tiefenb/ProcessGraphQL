<?php namespace ProcessWire\GraphQL\InputType;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\InputObjectType;
use ProcessWire\Template;
use ProcessWire\Page;
use ProcessWire\GraphQL\Cache;
use ProcessWire\GraphQL\Utils;
use ProcessWire\GraphQL\InputType\PageCreateInputType;

class PageUpdateInputType
{
  public static function getName(Template $template)
  {
    return Utils::normalizeTypeName("{$template->name}UpdateInput");
  }
  
  public static function &type(Template $template)
  {  
    $type =& Cache::type(self::getName($template), function () use ($template) {
      return new InputObjectType([
        'name' => self::getName($template),
        'description' => "UpdateInputType for pages with template `{$template->name}`.",
        'fields' => self::getFields($template),
      ]);
    });
   
    return $type;
  }

  public static function getFields(Template $template)
  {
    $fields = [];

    // add the page id
    $fields[] = [
      'name' => 'id',
      'type' => Type::nonNull(Type::id()),
      'description' => 'ProcessWire Page id of the page you want to update.',
    ];

    // add built in fields
    $fields = array_merge($fields, PageCreateInputType::getBuiltInFields());

    // add template fields
    $fields = array_merge($fields, PageCreateInputType::getTemplateFields($template));

    return $fields;
  }

  public static function setValues(Page $page, array $values) {
    PageCreateInputType::setValues($page, $values);
  }
}

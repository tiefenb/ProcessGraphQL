<?php namespace ProcessWire\GraphQL\InputType;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use ProcessWire\Field;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Error\ValidationError;
use ProcessWire\GraphQL\Utils;
use ProcessWire\Page;
use ProcessWire\Template;

class PageCreateInputType
{
  /**
   * Input fields that are not supported for now.
   */
  private const UNSUPPORTED_FIELDTYPES = [
    'FieldtypeFile',
    'FieldtypeImage',
  ];

  public static function getName(Template $template): string
  {
    return Utils::normalizeTypeName("{$template->name}CreateInput");
  }

  public static function type(Context $context, Template $template): InputObjectType
  {
    return $context->types->type(self::getName($template), fn() => new InputObjectType([
      'name' => self::getName($template),
      'description' => "CreateInputType for pages with template `{$template->name}`.",
      'fields' => fn() => self::getFields($context, $template),
    ]));
  }

  public static function getFields(Context $context, Template $template): array
  {
    // add built in fields and template fields
    $fields = [...self::getBuiltInFields(), ...self::getTemplateFields($context, $template)];

    // mark required fields as nonNull
    return self::markRequiredTemplateFields($fields, $template);
  }

  /**
   * The legal template fields the user can edit, as input fields.
   */
  public static function getTemplateFields(Context $context, Template $template): array
  {
    $fields = [];

    $legalFieldsName = implode('|', $context->module->legalFields);
    foreach ($template->fields->find("name=$legalFieldsName") as $field) {
      if (in_array($field->type->className(), self::UNSUPPORTED_FIELDTYPES, true)) {
        continue;
      }

      // skip the fields that user has no edit permission to
      if (!$context->permissions->canEditField($field, $template)) {
        continue;
      }

      // ignore the field if it has no corresponding Graphql class
      $f = Utils::pwFieldToGraphqlClass($field);
      if ($f === null) {
        continue;
      }

      $inputField = $f::inputField($context, $field);
      $fields[$inputField['name']] = $inputField;
    }

    return $fields;
  }

  public static function getBuiltInFields(): array
  {
    return [
      'parent' => [
        'name' => 'parent',
        'type' => Type::string(),
        'description' => 'Id or the path of the parent page.',
      ],
      'name' => [
        'name' => 'name',
        'type' => Type::string(),
        'description' => 'ProcessWire page name.',
      ],
    ];
  }

  public static function markRequiredTemplateFields(array $fields, Template $template): array
  {
    // mark parent and name as required if they are set
    foreach (['parent', 'name'] as $name) {
      if (isset($fields[$name])) {
        $fields[$name]['type'] = Type::nonNull($fields[$name]['type']);
      }
    }

    // mark required fields as required
    foreach ($template->fields as $field) {
      if (isset($fields[$field->name]) && $field->required) {
        $fields[$field->name]['type'] = Type::nonNull($fields[$field->name]['type']);
      }
    }

    return $fields;
  }

  /**
   * Sets the client values on the page, checking field edit permissions.
   *
   * @throws ValidationError
   */
  public static function setValues(Context $context, Page $page, array $values): void
  {
    $fields = $context->fields();
    foreach ($values as $fieldName => $value) {
      $field = $fields->get($fieldName);

      // skip if field cannot be found
      if (!$field instanceof Field) {
        continue;
      }

      // check if user has permission to edit this field
      if (!$context->permissions->canEditField($field, $page->template)) {
        throw new ValidationError("You are not allowed to update field '{$field->name}'");
      }

      // skip if GraphQL class of a field is not found
      $f = Utils::pwFieldToGraphqlClass($field);
      if ($f === null) {
        continue;
      }

      $f::setValue($context, $page, $field, $value);
    }
  }
}

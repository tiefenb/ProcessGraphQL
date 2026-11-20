<?php namespace ProcessWire\GraphQL;

use ProcessWire\Field;
use ProcessWire\Template;
use ProcessWire\TemplatesArray;

/**
 * Access checks for the current user of a Context.
 *
 * The permission filtered template lists are memoized per user for the
 * lifetime of the Context (one GraphQL execution).
 */
final class Permissions
{
  public const pageAddPermission = "page-add";
  public const pageCreatePermission = "page-create";
  public const pageDeletePermission = "page-delete";
  public const pageEditPermission = "page-edit";
  public const pageMovePermission = "page-move";
  public const pageViewPermission = "page-view";
  public const pageEditCreatedPermission = "page-edit-created";
  public const pageEditTrashCreatedPermission = "page-edit-trash-created";

  /** @var array<string, TemplatesArray> */
  private array $memo = [];

  public function __construct(private readonly Context $context)
  {
  }

  /**
   * Tells if pages using this template can be viewed by the current user.
   */
  public function canView(Template $template): bool
  {
    $user = $this->context->user();

    if ($user->isSuperuser()) {
      return true;
    }

    return $this->definesAccess($template)
      && $user->hasPermission(self::pageViewPermission, $template);
  }

  /**
   * Tells if the current user can create pages using the given template.
   */
  public function canCreate(Template $template): bool
  {
    $user = $this->context->user();
    $templates = $this->context->templates();

    // can't create a page if no parent allowed
    if ($template->noParents == 1) {
      return false;
    }

    // can't create if allowed parents are not legal
    if (!$template->noParents && count($template->parentTemplates)) {
      // filter out the parents that has noChildren checked or
      // has configured childTemplates without the target template
      $parentTemplates = array_filter(
        $template->parentTemplates,
        function ($templateId) use ($template, $templates) {
          $parentTemplate = $templates->get($templateId);
          if ($parentTemplate->noChildren) {
            return false;
          }
          return !count($parentTemplate->childTemplates)
            || in_array($template->id, $parentTemplate->childTemplates);
        }
      );

      // get templates that user can add pages to
      $addTemplates = $this->getAddTemplates()->explode("id");
      if (empty(array_intersect($addTemplates, $parentTemplates))) {
        return false;
      }
    }

    // can't create a page if one of the required fields is not legal
    $legalFields = array_flip($this->context->module->legalFields);
    $requiredFields = $template->fields->find("required=1");
    foreach ($requiredFields as $field) {
      if (!isset($legalFields[$field->name])) {
        return false;
      }
    }

    // superuser can create all pages
    if ($user->isSuperuser()) {
      return true;
    }

    if (!$this->definesAccess($template)) {
      return false;
    }

    if (!$user->hasPermission(self::pageCreatePermission, $template)) {
      return false;
    }

    // can't create a page if one of the required fields is not editable by the user for this template
    foreach ($requiredFields as $field) {
      if (!$this->canEditField($field, $template)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Tells if the user can edit pages with the given template.
   */
  public function canEdit(Template $template): bool
  {
    $user = $this->context->user();

    if ($user->isSuperuser()) {
      return true;
    }

    return $this->definesAccess($template)
      && $user->hasPermission(self::pageEditPermission, $template);
  }

  /**
   * Tells if the user can delete pages with the given template.
   */
  public function canDelete(Template $template): bool
  {
    $user = $this->context->user();

    if ($user->isSuperuser()) {
      return true;
    }

    return $this->definesAccess($template)
      && $user->hasPermission(self::pageDeletePermission, $template);
  }

  /**
   * Tells if the user can trash pages with the given template.
   */
  public function canTrash(Template $template): bool
  {
    $user = $this->context->user();

    if ($user->isSuperuser()) {
      return true;
    }

    if (!$this->definesAccess($template)) {
      return false;
    }

    return $user->hasPermission(self::pageDeletePermission, $template)
      || $user->hasPermission(self::pageEditTrashCreatedPermission, $template);
  }

  /**
   * Tells if pages can be added as children of pages with the given template.
   */
  public function canAdd(Template $template): bool
  {
    $user = $this->context->user();

    // can't add a page if no child is allowed
    if ($template->noChildren == 1) {
      return false;
    }

    // can't add a page if the allowed childTemplates are not legal
    if ($template->noChildren == 0 && count($template->childTemplates)) {
      if (!count(array_intersect($this->getTemplateIds(), $template->childTemplates))) {
        return false;
      }
    }

    if ($user->isSuperuser()) {
      return true;
    }

    return $this->definesAccess($template)
      && $user->hasPermission(self::pageAddPermission, $template);
  }

  /**
   * Tells if the field can be edited by the user within the context of the given template.
   */
  public function canEditField(Field $field, Template $template): bool
  {
    return $this->hasFieldPermission("edit", $field, $template);
  }

  /**
   * Tells if the field can be viewed by the user within the context of the given template.
   */
  public function canViewField(Field $field, Template $template): bool
  {
    return $this->hasFieldPermission("view", $field, $template);
  }

  /**
   * Determines whether the current user has the given permission ('view' or
   * 'edit') on $field within $template's context.
   */
  public function hasFieldPermission(string $permission, Field $field, Template $template): bool
  {
    $user = $this->context->user();

    if ($user->isSuperuser()) {
      return true;
    }

    // cannot view/edit if access rules are not defined
    $field = $template->fields->getFieldContext($field);
    if (!$this->definesAccess($field)) {
      return false;
    }

    $roles = $permission . "Roles";
    $allowedRoleIds = $field->$roles;

    return array_any(
      $user->roles->explode("id"),
      fn($roleId) => in_array($roleId, $allowedRoleIds)
    );
  }

  /**
   * Tells if the template or field has access control defined.
   */
  public function definesAccess(Template|Field $context): bool
  {
    return (bool) $context->useRoles;
  }

  /**
   * Returns legal templates. The ones marked in the module settings.
   */
  public function getTemplates(): TemplatesArray
  {
    $names = implode("|", $this->context->module->legalTemplates);
    return $this->context->templates()->find("name=$names");
  }

  /**
   * Returns the ids of the legal templates.
   *
   * @return int[]
   */
  public function getTemplateIds(): array
  {
    return $this->getTemplates()->explode("id");
  }

  /**
   * Returns the legal templates that match the predicator.
   *
   * @param callable(Template): bool $predicator
   */
  public function filterTemplatesByPermission(callable $predicator): TemplatesArray
  {
    $templates = $this->getTemplates();
    foreach ($templates as $template) {
      if (!$predicator($template)) {
        $templates->remove($template);
      }
    }
    return $templates;
  }

  /** Templates that can be viewed by the current user. */
  public function getViewTemplates(): TemplatesArray
  {
    return $this->memoize("view", $this->canView(...));
  }

  /** Templates whose pages can be created by the current user. */
  public function getCreateTemplates(): TemplatesArray
  {
    return $this->memoize("create", $this->canCreate(...));
  }

  /** Templates whose pages can be edited by the current user. */
  public function getEditTemplates(): TemplatesArray
  {
    return $this->memoize("edit", $this->canEdit(...));
  }

  /** Templates whose pages can be deleted by the current user. */
  public function getDeleteTemplates(): TemplatesArray
  {
    return $this->memoize("delete", $this->canDelete(...));
  }

  /** Templates whose pages can be moved to the trash by the current user. */
  public function getTrashTemplates(): TemplatesArray
  {
    return $this->memoize("trash", $this->canTrash(...));
  }

  /** Templates whose pages the current user can add children to. */
  public function getAddTemplates(): TemplatesArray
  {
    return $this->memoize("add", $this->canAdd(...));
  }

  /**
   * Makes the legal fields accessible via the ProcessWire API.
   */
  public function turnOnApiAccess(): void
  {
    $fields = $this->context->fields();
    foreach ($this->context->module->legalFields as $fieldName) {
      $field = $fields->get($fieldName);
      if ($field instanceof Field) {
        $field->flags = $field->flags | Field::flagAccessAPI;
      }
    }
  }

  /**
   * Memoized per user, because `login` and `logout` can change the user
   * during an execution.
   */
  private function memoize(string $kind, callable $predicator): TemplatesArray
  {
    $key = $kind . ':' . $this->context->user()->id;
    return $this->memo[$key] ??= $this->filterTemplatesByPermission($predicator);
  }
}

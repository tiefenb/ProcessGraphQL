<?php namespace ProcessWire\GraphQL\Field\Mutation;

use GraphQL\Type\Definition\Type;
use ProcessWire\Template;
use ProcessWire\NullPage;
use ProcessWire\GraphQL\Context;
use ProcessWire\GraphQL\Utils;
use ProcessWire\GraphQL\Permissions;
use ProcessWire\GraphQL\Type\PageType;
use ProcessWire\GraphQL\Error\ExecutionError;
use ProcessWire\GraphQL\Error\ValidationError;
use ProcessWire\GraphQL\InputType\PageUpdateInputType;
use ProcessWire\WireException;

class UpdatePage
{
  public static function field(Context $context, Template $template): array
  {
    return [
      'name' => self::name($template),
      'description' => self::description($template),
      'type' => PageType::type($context, $template),
      'args' => [
        'page' => Type::nonNull(PageUpdateInputType::type($context, $template)),
      ],
      'resolve' => fn($root, array $args, Context $context) => self::resolve($context, $args, $template),
    ];
  }

  public static function name($template)
  {
    return Utils::normalizeFieldName("update_{$template->name}");
  }

  public static function description(Template $template)
  {
    return "Allows you to update Pages with template `{$template->name}`.";
  }

  public static function resolve(Context $context, array $args, Template $template)
  {
    // prepare neccessary variables
    $pages = $context->pages();
    $user = $context->user();
    $sanitizer = $context->sanitizer();
    $values = (array) $args['page'];
    $id = (int) $values['id'];
    $p = $pages->get($id);

    // make sure the target page exists and uses the template this
    // mutation was generated (and permission checked) for
    if ($p instanceof NullPage || $p->template->id !== $template->id) {
      throw new ValidationError("Could not find the page `$id` to update.");
    }

    // make sure ProcessWire itself considers the page editable by the user
    // (covers system pages, page-edit-created and per-page access rules)
    if (!$p->editable()) {
      throw new ValidationError("You are not allowed to update the page '{$p->id}'.");
    }

    // if page-edit-created permission is installed and user has that permission
    // make sure that user can edit this page
    $pageEditCreatedPermission = $context->wire->permissions->get('page-edit-created');
    if (
      $pageEditCreatedPermission->id &&
      $user->hasPermission($pageEditCreatedPermission) &&
      $p->createdUser->id !== $user->id &&
      !$user->isSuperuser()
    ) {
      throw new ValidationError("You are not allowed to update the page '{$p->id}'.");
    }

    $p->of(false);

    /*********************************************\
     *                                           *
     * Don't ever take sides against the family! *
     *                                           *
    \*********************************************/
    $parent = null;
    if (isset($values['parent'])) {

      // find the parent
      $parentSelector = $values['parent'];
      if (!ctype_digit($parentSelector)) {
        $parentSelector = $sanitizer->pagePathName($parentSelector);
      }
      $parent = $pages->get($parentSelector);

      // if no parent then no good. No child should born without a parent!
      if (!$parent || $parent instanceof NullPage) {
        throw new ValidationError("Could not find the `parent` page with `$parentSelector`.");
      }

      // if user is trying to move the page
      // make sure user has page-move permission
      $pageMovePermission = $context->wire->permissions->get(Permissions::pageMovePermission);
      if (
        $p->parentID !== $parent->id &&
        !$user->hasPermission($pageMovePermission, $p->template)
      ) {
        throw new ValidationError("You are not allowed to move the page '{$p->id}'.");
      }

      // make sure user is allowed to add children to this parent
      $addTemplates = $context->permissions->getAddTemplates();
      if (!$addTemplates->has($parent->template)) {
        throw new ValidationError("You are not allowed to add children to the parent: '$parentSelector'.");
      }

      // make sure parent is allowed as a parent for this page
      $parentTemplates = $template->parentTemplates;
      if (count($parentTemplates) && !in_array($parent->template->id, $parentTemplates)) {
        throw new ValidationError("`parent` is not allowed as a parent.");
      }

      // make sure parent is allowed to have children
      if ($parent->template->noChildren === 1) {
        throw new ValidationError("`parent` is not allowed to have children.");
      }

      // make sure the page is allowed as a child for parent
      $childTemplates = $parent->template->childTemplates;
      if (count($childTemplates) && !in_array($template->id, $childTemplates)) {
        throw new ValidationError("not allowed to be a child for `parent`.");
      }

      $p->parent = $parent;
    }

    if (isset($values['name'])) {
      
      // check if the name is valid
      $name = $sanitizer->pageName($values['name']);
      if (!$name) {
        throw new ValidationError('value for `name` field is invalid,');
      }
      
      // find out if the name is taken
      if (!isset($values['parent'])) {
        $parent = $p->parent;
      }
      $taken = $pages->find("parent=$parent, name=$name")->count();
      if ($taken) {
        throw new ValidationError("name '$name' is already taken.");
      }

      $p->name = $name;
    }

    // unset the id because you cannot update the id
    unset($values['id']);

    // unset the parent and name as we set them above
    unset($values['parent']);
    unset($values['name']);

    PageUpdateInputType::setValues($context, $p, $values);

    // save the page to db
    try {
      $p->save();
    } catch (WireException $err) {
      throw new ExecutionError($err->getMessage());
    }
    return $pages->get("$p");
  }
}


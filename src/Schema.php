<?php namespace ProcessWire\GraphQL;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema as GraphQLSchema;
use ProcessWire\GraphQL\Field\Auth\Login;
use ProcessWire\GraphQL\Field\Auth\Logout;
use ProcessWire\GraphQL\Field\Debug\DbQuery;
use ProcessWire\GraphQL\Field\Language;
use ProcessWire\GraphQL\Field\Mutation\CreatePage;
use ProcessWire\GraphQL\Field\Mutation\TrashPage;
use ProcessWire\GraphQL\Field\Mutation\UpdatePage;
use ProcessWire\GraphQL\Type\PageArrayType;
use ProcessWire\GraphQL\Type\UserType;

/**
 * Builds the GraphQL schema for a Context.
 *
 * The schema depends on the permissions of the context's user, so it is built
 * per context and never cached statically.
 */
final class Schema
{
  public static function build(Context $context): GraphQLSchema
  {
    $schema = [];

    $query = self::buildQuery($context);
    if ($query) {
      $schema['query'] = $query;
    }

    $mutation = self::buildMutation($context);
    if ($mutation) {
      $schema['mutation'] = $mutation;
    }

    // Let the user or other modules modify the schema.
    $schema = $context->module->modifySchema($schema, $context);

    return new GraphQLSchema($schema);
  }

  public static function buildQuery(Context $context): ?ObjectType
  {
    $module = $context->module;
    $queryFields = [];

    // legal templates
    foreach ($context->permissions->getViewTemplates() as $template) {
      $queryFields[] = PageArrayType::field($context, $template);
    }

    // User. The `me`
    if ($module->meQuery) {
      $queryFields[] = [
        'name' => 'me',
        'description' => 'The current user of the app.',
        'type' => UserType::type($context),
        'resolve' => fn($root, array $args, Context $context) => $context->user(),
      ];
    }

    // Auth
    if ($module->authQuery) {
      $queryFields[] = $context->user()->isLoggedin()
        ? Logout::field($context)
        : Login::field($context);
    }

    // Debugging
    if ($context->config()->debug) {
      $queryFields[] = DbQuery::field();
    }

    // Language
    if ($context->languages()) {
      $queryFields[] = Language::field();
    }

    // let the user modify the query operation
    $queryFields = $module->getQueryFields($queryFields, $context);

    if (empty($queryFields)) {
      return null;
    }

    return new ObjectType([
      'name' => 'Query',
      'fields' => $queryFields,
    ]);
  }

  public static function buildMutation(Context $context): ?ObjectType
  {
    $permissions = $context->permissions;
    $mutationFields = [];

    foreach ($permissions->getCreateTemplates() as $template) {
      $mutationFields[] = CreatePage::field($context, $template);
    }

    foreach ($permissions->getEditTemplates() as $template) {
      $mutationFields[] = UpdatePage::field($context, $template);
    }

    if (count($permissions->getTrashTemplates())) {
      $mutationFields[] = TrashPage::field($context);
    }

    // let the user modify the mutation operation
    $mutationFields = $context->module->getMutationFields($mutationFields, $context);

    if (empty($mutationFields)) {
      return null;
    }

    return new ObjectType([
      'name' => 'Mutation',
      'fields' => $mutationFields,
    ]);
  }
}

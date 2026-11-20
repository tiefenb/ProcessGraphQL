<?php

namespace ProcessWire\GraphQL;

use ProcessWire\Config;
use ProcessWire\Fields;
use ProcessWire\Languages;
use ProcessWire\Pages;
use ProcessWire\ProcessGraphQL;
use ProcessWire\ProcessWire;
use ProcessWire\Sanitizer;
use ProcessWire\Session;
use ProcessWire\Templates;
use ProcessWire\User;
use ProcessWire\WireDatabasePDO;

/**
 * State of one GraphQL execution.
 *
 * A new Context is created for every executed request and passed to graphql-php
 * as `contextValue`, so every resolver receives it as its third argument:
 *
 * ```php
 * 'resolve' => function ($value, array $args, Context $context, ResolveInfo $info) {
 *   return $context->pages()->find(...);
 * }
 * ```
 *
 * The schema is built for a context as well (see Schema::build()), because it
 * depends on the permissions of the current user. Nothing is kept in static
 * properties, so multiple ProcessWire instances and long running PHP workers
 * do not share state between requests.
 */
final class Context
{
  /** Access checks of the current user, memoized for this context. */
  public readonly Permissions $permissions;

  /** Buffers page ids for batch loading (avoids N+1 queries). */
  public readonly PagesBuffer $buffer;

  /** Built GraphQL types and field definitions of this context's schema. */
  public readonly TypeRegistry $types;

  /**
   * Parsed (sanitized) client selectors, keyed by selector and limit.
   *
   * @var array<string, string>
   */
  public array $selectors = [];

  /**
   * Raw file/image rows loaded in batches, keyed by field name and page id.
   *
   * @var array<string, array<int|string, array<int, array<string, mixed>>>>
   */
  public array $files = [];

  public function __construct(
    public readonly ProcessWire $wire,
    public readonly ProcessGraphQL $module,
  ) {
    $this->permissions = new Permissions($this);
    $this->buffer = new PagesBuffer($this);
    $this->types = new TypeRegistry();
  }

  /**
   * The current user. Not stored, because `login`/`logout` can change it
   * during an execution.
   */
  public function user(): User
  {
    return $this->wire->user;
  }

  public function pages(): Pages
  {
    return $this->wire->pages;
  }

  public function fields(): Fields
  {
    return $this->wire->fields;
  }

  public function templates(): Templates
  {
    return $this->wire->templates;
  }

  public function sanitizer(): Sanitizer
  {
    return $this->wire->sanitizer;
  }

  public function session(): Session
  {
    return $this->wire->session;
  }

  public function config(): Config
  {
    return $this->wire->config;
  }

  public function database(): WireDatabasePDO
  {
    return $this->wire->database;
  }

  public function languages(): ?Languages
  {
    $languages = $this->wire->languages;
    return $languages instanceof Languages ? $languages : null;
  }
}

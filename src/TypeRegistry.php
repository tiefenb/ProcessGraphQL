<?php

namespace ProcessWire\GraphQL;

/**
 * Store for the GraphQL types and field definitions of one schema.
 *
 * Every type must only be built once per schema, graphql-php requires type
 * instances to be unique. Circular references between types are resolved by
 * graphql-php itself, as long as `fields` are given as closures.
 */
final class TypeRegistry
{
  /** @var array<string, mixed> */
  private array $types = [];

  /** @var array<string, mixed> */
  private array $fields = [];

  /** @var array<string, true> */
  private array $building = [];

  /**
   * Returns the type with the given name, building it on first access.
   */
  public function type(string $name, callable $build): mixed
  {
    return $this->remember($this->types, "type:$name", $name, $build);
  }

  /**
   * Returns the field definition with the given name, building it on first access.
   */
  public function field(string $name, callable $build): mixed
  {
    return $this->remember($this->fields, "field:$name", $name, $build);
  }

  public function hasType(string $name): bool
  {
    return array_key_exists($name, $this->types);
  }

  private function remember(array &$store, string $buildKey, string $name, callable $build): mixed
  {
    if (array_key_exists($name, $store)) {
      return $store[$name];
    }

    if (isset($this->building[$buildKey])) {
      throw new \LogicException(
        "Circular build of '$name'. Define the fields of the involved types as closures."
      );
    }

    $this->building[$buildKey] = true;
    try {
      return $store[$name] = $build();
    } finally {
      unset($this->building[$buildKey]);
    }
  }
}

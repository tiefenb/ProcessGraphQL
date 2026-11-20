<?php

namespace ProcessWire\GraphQL;

/**
 * Per-request store for the built GraphQL types and field definitions.
 *
 * Every type must only be built once, graphql-php requires type instances
 * to be unique per schema. Circular references between types are resolved
 * by graphql-php itself, as long as `fields` are given as closures.
 */
class Cache
{
  /** @var array<string, mixed> */
  private static array $typeStore = [];

  /** @var array<string, mixed> */
  private static array $fieldStore = [];

  /** @var array<string, true> */
  private static array $building = [];

  public static function type(string $name, ?callable $build = null)
  {
    return self::remember(self::$typeStore, "type:$name", $name, $build);
  }

  public static function field(string $name, ?callable $build = null)
  {
    return self::remember(self::$fieldStore, "field:$name", $name, $build);
  }

  private static function remember(array &$store, string $buildKey, string $name, ?callable $build)
  {
    if (array_key_exists($name, $store)) {
      return $store[$name];
    }

    if ($build === null) {
      throw new \InvalidArgumentException("The second argument for Cache::type()/field() should be a callable.");
    }

    if (isset(self::$building[$buildKey])) {
      throw new \LogicException(
        "Circular build of '$name'. Define the fields of the involved types as closures."
      );
    }

    self::$building[$buildKey] = true;
    try {
      return $store[$name] = $build();
    } finally {
      unset(self::$building[$buildKey]);
    }
  }

  public static function clearType()
  {
    self::$typeStore = [];
  }

  public static function clearField()
  {
    self::$fieldStore = [];
  }

  public static function clear()
  {
    self::clearType();
    self::clearField();
    self::$building = [];
    Permissions::clearCache();
  }
}

<?php

namespace ProcessWire\GraphQL;

class Cache
{
  /**
   * Type caching
   */
  private static array $typeStore = [];

  public static function &type(string $name, ?callable $build = null)
  {
    if (isset(self::$typeStore[$name])) {
      return self::$typeStore[$name];
    }

    if ($build === null || !is_callable($build)) {
      throw new \Exception("The second argument for Cache::type() should be a callable.");
    }

    self::$typeStore[$name] = Utils::placeholder();
    self::$typeStore[$name] = $build();
    return self::$typeStore[$name];
  }

  public static function clearType()
  {
    self::$typeStore = [];
  }


  /**
   * Field caching
   */
  private static array $fieldStore = [];

  public static function &field(string $name, ?callable $build = null)
  {
    if (isset(self::$fieldStore[$name])) {
      return self::$fieldStore[$name];
    }

    if ($build === null || !is_callable($build)) {
      throw new \Exception("The second argument for Cache::field() should be a callable.");
    }

    self::$fieldStore[$name] = Utils::placeholder();
    self::$fieldStore[$name] = $build();
    return self::$fieldStore[$name];
  }

  public static function clearField()
  {
    self::$fieldStore = [];
  }


  public static function clear()
  {
    self::clearType();
    self::clearField();
  }
}

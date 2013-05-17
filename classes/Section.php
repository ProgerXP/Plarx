<?php namespace Px;

class Section extends \Laravel\Section {
  //= true if section is defined, false if it's not
  static function has($section) {
    return isset( static::$sections[$section] );
  }
}
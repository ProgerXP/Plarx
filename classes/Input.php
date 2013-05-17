<?php namespace Px;

class Input extends \Laravel\Input {
  // Unlike default accepts keys without an array.
  //= hash of input values
  //
  //? only('login', 'email', 'remember') == only(array('login', 'email', 'remember'))
  static function only($key_1 = null) {
    return parent::only( is_array($key_1) ? $key_1 : func_get_args() );
  }

  // Throws ENoInput if $key wasn't passed with the request.
  //= mixed input variable
  static function must($key) {
    $value = static::get($key);
    if (!isset($value)) { throw new ENoInput($key); }
    return $value;
  }
}
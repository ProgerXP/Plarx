<?php namespace Px;
/*
  Part of Plarx | https://github.com/ProgerXP/Plarx
*/

/*-----------------------------------------------------------------------
| HTTP STATUS CODES
|----------------------------------------------------------------------*/

// These constants can be used in DoubleEdge responses, for example:
//
// function get_index($id) {
//   if ($model = AModel::find($id)) {
//     return $model;
//   } else {
//     return E_NONE;
//   }
// }

define('Px\\E_OK',        200);
define('Px\\E_UNCHANGED', 304);
define('Px\\E_INPUT',     400);
define('Px\\E_UNAUTH',    401);
define('Px\\E_DENIED',    403);
define('Px\\E_NONE',      404);
define('Px\\E_GONE',      410);
define('Px\\E_SERVER',    500);

/*-----------------------------------------------------------------------
| CUSTOM EXCEPTIONS
|----------------------------------------------------------------------*/

// Generic exception, parent for all Plarx exceptions.
class EPlarx extends \Exception { }

// Exception used for JSON-related errors such as string decoding or encoding.
class EJSON extends EPlarx {
  //= int     json_last_error() result
  //= null    if unknown
  public $jsonError;

  //* $code int, null - json_last_error() code, if available.
  function __construct($msg, $code = null) {
    $this->jsonError = $code;
    func_num_args() > 1 and $msg .= " - error code $code.";
    parent::__construct($msg);
  }
}

// Exception used when a required request variable is missing.
class ENoInput extends EPlarx {
  //= str     input variable name that wasn't passed
  //= null    unspecified var name
  public $key;

  //* $key str, null - name of missing request variable, if available.
  function __construct($key = null) {
    "$key" === '' or $this->key = $key;
    $name = "$key" === '' ? '' : " [$key]";
    parent::__construct("No input variable$name given.");
  }
}

// Exception used when client is expected to be authorized but it isn't.
class ENoAuth extends EPlarx {
  // Reference to controller that has caused this exception.
  //
  //= object
  //= str     name like 'bndl::ctl.sub@actn'
  public $controller;

  //* $controller object, str, null - controller requiring authentication, if available.
  function __construct($controller = null) {
    $this->controller = $controller;
    is_object($controller) and $controller = get_class($controller);
    parent::__construct("Controller [$controller] expects an authorized user.");
  }
}

// Exception used when an event didn't return expected value or some other conditions
// have not been met.
class EEvent extends EPlarx { }

/*-----------------------------------------------------------------------
| GLOBAL FUNCTIONS
|----------------------------------------------------------------------*/

// function (str $prop)
//
// Creates function ($obj) that returns $obj->$prop or null if $obj isn't an object.
// Useful for array_map(), array_filter() and the likes.
//
//= Closure ($obj)
//
//? array_omit(prop('available'), $goods);
//    // removes objects from $goods that have falsy ->$available property
//? array_map(prop('id'), $models);
//    // returns array containing each $models' member ID (ignoring non-objects)
//
// function (str $prop, array $array)
//
// Maps $array with the function produced by the first version ($array-less).
//
//= array
//
//? prop('id', $models);
//    // identical to array_map(prop('id'), $models);
function prop($prop, array $toMap = null) {
  if (func_num_args() > 1) {
    return array_map(prop($prop), (array) $toMap);
  } else {
    return function ($obj) use ($prop) {
      return is_object($obj) ? $obj->$prop : null;
    };
  }
}

// function (str $func)
//
// Creates function ($obj) that returns the result of calling $obj->$func() or
// null if $obj isn't an object.
// Useful for array_map(), array_filter() and the likes.
//
//= Closure ($obj)
//
//? array_filter(func('dirty'), $models);
//    // keeps $models' objects which method dirty() returns non-falsy value
//? array_map(func('to_array'), $models);
//    // converts each $models' object into array by calling their to_array() method
//
// function (str $func, array $array)
//
// Maps $array with the function produced by the first version ($array-less).
//
//= array
//
//? prop('to_array', $models);
//    // identical to array_map(func('to_array'), $models);
function func($func, array $toMap = null) {
  if (func_num_args() > 1) {
    return array_map(func($func), (array) $toMap);
  } else {
    return function ($obj) use ($func) {
      return is_object($obj) ? $obj->$func() : null;
    };
  }
}

// The opposite of array_filter(). Preserves array keys.
//
//* $func callable ($item), null - if given removes items from $array to which
//  $func($item) returned a non-falsy value; if omitted keeps only falsy members.
//
//? $missingPerms = array_keys(array_omit(array('perm1' => true, 'perm2' => false)));
//    //=> array('perm2')
//? array_omit(range(-3, 3), function ($n) { return $n > 1; });
//    //=> array(2, 3)
function array_omit($array, $func = null) {
  return array_filter($array, function ($value) use ($func) {
    return $func ? !call_user_func($func, $value) : !$value;
  });
}

// Converts $value to array. Unlike (array) will return empty array on null $value
// and array($value) on object $value. See also arrizeAny().
//
//* $value mixed
//* $key mixed - used when $value is not an array to assign initial member's key.
//
//= array
//
//? arrize(null);                     //=> array()
//? arrize(array());                  //=> array()
//? arrize(array('foo'));             //=> array('foo')
//? arrize('foo');                    //=> array('foo')
//? arrize('foo', 'bar');             //=> array('bar' => 'foo')
//? arrize(array('foo'), 'bar');      //=> array('foo')
//? arrize(new stdClass);             //=> array(new stdClass)
//? arrize(new stdClass, 4444);       //=> array(4444 => new stdClass)
//? arrize(function () { }, 1);       //=> array(1 => function () { })
function arrize($value, $key = 0) {
  return $value === null ? array() : arrizeAny($value, $key);
}

// Converts given value to array. Unlike (array) it doesn't convert objects and
// doesn't cause Fatal Error when given a Closure. Returns array(null) on null $value.
//
// See also arrize().
//
//* $value mixed
//* $key mixed - used when $value is not an array to assign initial member's key.
//
//= array
//
//? arrize(null);                     //=> array(null)
//? plus all other examples from arrize()
function arrizeAny($value, $key = 0) {
  return is_array($value) ? $value : array($key => $value);
}

// Changes array keys by invoking $func as ($keys, $value) and using its return
// value as new key.
//
//? array_set_keys(array('a' => 'b'), function ($k) { return "-$k-"; });
//    //=> array('-a-' => 'b')
function array_set_keys(array $array, $func) {
  $keys = array();
  foreach ($array as $key => &$value) { $keys[] = $func($key, $value); }
  return $array ? array_combine($keys, $array) : array();
}

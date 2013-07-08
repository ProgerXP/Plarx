<?php namespace Px;

// Extends standard Laravel event facility with a few useful methods.
class Event extends \Laravel\Event {
  // Like listen() but adds $callback as the first handler, in front of others.
  static function preview($event, $callback) {
    $pool = &static::$events[$event];
    $pool = arrize($pool);
    array_unshift($pool, $callback);
  }

  // Unlike default emits a log message and wraps any but null $parameters into array().
  static function fire($events, $parameters = array(), $halt = false) {
    $events = (array) $events;
    $parameters = arrizeAny($parameters);

    $s = count($events) == 1 ? '' : 's';
    $until = $halt ? ' (first result)' : '';
    \Log::info("Event$s$until: ".join(', ', $events).
               " ( ".static::paramStr($parameters)." )");

    return parent::fire($events, $parameters, $halt);
  }

  static function paramStr($params) {
    $toStr = function ($value) {
      if (!is_scalar($value)) {
        return gettype($value);
      } elseif ($value === '') {
        return "''";
      } elseif (strlen("$value") > 20) {
        return substr("$value", 0, 15).'...';
      } else {
        return $value;
      }
    };

    return join(', ', array_map($toStr, arrizeAny($params)));
  }

  // function ( $event [, $parameters = array()] , Closure $checker )
  static function result($event, $parameters, $checker = null) {
    if (func_num_args() == 2) {
      $checker = $parameters;
      $parameters = array();
    }

    $result = static::until($event, $parameters);

    if ($msg = $checker($result) and $msg !== true) {
      throw new \EEvent("No event handlers attached to [$event] or they".
                         " have generated $msg: ".var_export($result, true));
    } else {
      return $result;
    }
  }

  // Fires $type.insert and $type.inserted events to save given $model.
  static function insertModel($model, $type) {
    $class = get_class($model);
    $ok = static::until("$type.insert", array(&$model));

    if ($ok and $model instanceof $class) {
      static::fire("$type.inserted", $model);
      return $model;
    } else {
      throw new \EEvent("Cannot insert new record for $model.");
    }
  }
}
<?php namespace Px;

// Enchances default \Laravel profiler:
// * adds call trace with arguments to all issued SQLs
// * suppresses outputting of the profiler toolbar when processing a true
//   AJAX request (Request::ajax() === true)
class Profiler extends \Laravel\Profiling\Profiler {
  //= int how many calls include in the SQL call trace (from the end)
  static $sqlTraceLimit = 14;
  //= int adds an ellipsis for calls given more than this number of arguments
  static $sqlMaxTraceArgs = 3;

  static $override = array('laravel.log', 'laravel.query', 'laravel.done');

  static function attach() {
    parent::attach();
    $self = get_called_class();

    foreach (static::$override as $event) {
      $original = array_pop(Event::$events[$event]);

      Event::listen($event, function () use ($self, $original, $event) {
        list(, $func) = explode('.', $event, 2);
        $func = 'on'.ucfirst($func);

        $args = array_merge(array($original), func_get_args());
        return call_user_func_array(array($self, $func), $args);
      });
    }
  }

  static function onLog($original, $type, $message) {
    static::$data['logs'][] = array(HLEx::q($type), HLEx::q($message));
  }

  static function onQuery($original, $sql, $bindings, $time) {
    foreach ($bindings as $binding) {
      // DB::escape() is only present after Laravel 3.2.10.
      $binding = \DB::connection()->pdo->quote($binding);
      $sql = preg_replace('/\?/', $binding, $sql, 1);
    }

    $original = $sql;
    $sql = htmlspecialchars($sql, ENT_NOQUOTES, 'utf-8');

    foreach (static::$data['queries'] as $i => $seen) {
      if (array_get($seen, 2) === $original) {
        $sql = '<span style="color: red" title="Duplicated with #'.($i + 1).'">'.
               $sql.'</span>';
        break;
      }
    }

    $tracer = function ($call) {
      if ($class = array_get($call, 'class')) {
        $class = class_basename($class).array_pick($call, 'type');
      }

      $args = array();
      foreach ((array) array_get($call, 'args') as $arg) {
        if ($arg === null or is_bool($arg)) {
          $arg = var_export($arg, true);
          $color = 'black';
        } elseif (is_scalar($arg)) {
          $color = (is_int($arg) or is_float($arg)) ? 'blue' : 'navy';
        } elseif (is_object($arg)) {
          $color = 'maroon';
          $arg = get_class($arg);
        } else {
          $color = 'gray';
          $arg = gettype($arg);
        }

        $args[] = '<span style="color: '.$color.'">'.HLEx::q($arg).'</span>';

        if (count($args) > Profiler::$sqlMaxTraceArgs) {
          $args[] = '&hellip;';
          break;
        }
      }

      $highlight = $class ? 'b_q' : 'q';
      $result = HLEx::$highlight($class.$call['function']).
                ' ('.join(', ', $args).')';

      $result = '<span style="display: inline-block; min-width: 70%">'.$result.'</span>';

      if ($file = &$call['file']) {
        $result .= "&larr; <span style='color: navy'>".basename($file).'</span>';
        isset($call['line']) and $result .= ':'.$call['line'];
      }

      return "\n  $result";
    };

    $trace = debug_backtrace();
    $removing = false;
    $last = null;

    while ($trace) {
      if (starts_with(array_get($trace[0], 'class'), 'Laravel\\Database\\')) {
        $removing = true;
        array_shift($trace);
      } elseif ($removing) {
        array_unshift($trace, $last);
        break;
      } else {
        $last = array_shift($trace);
      }
    }

    $trace = array_slice($trace, 0, Profiler::$sqlTraceLimit);
    $sql .= '  '.join(' ', array_map($tracer, $trace));

    static::$data['queries'][] = array($sql, $time, $original);
  }

  static function onDone($original, $response) {
    Request::ajax() === true or call_user_func_array($original, func_get_args());
  }
}

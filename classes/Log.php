<?php namespace Px;

class Log extends \Laravel\Log {
  // Unlike default skips certain types according to config 'error.log_skip'.
  static function write($type, $message, $pretty_print = false) {
    $type = strtoupper($type);

    if (!in_array($type, array_map('strtoupper', (array) \Config::get('error.log_skip')))) {
      parent::write($type, $message, $pretty_print);
    }
  }

	protected static function format($type, $message) {
		$msg = trim(parent::format($type, $message));
		$msg .= ' '.\URI::full();
		return $msg.PHP_EOL;
	}

  // Unlike default allows for Log::warn_MyObject() calling name.
  //
  //= str message that was written
  static function __callStatic($method, $parameters) {
    $method = strtok($method, '_');
    $object = strtok(null);
    $object and $object = "$object: ";
    $msg = $object.reset($parameters);

		$parameters[1] = empty($parameters[1]) ? false : $parameters[1];
		static::write($method, $msg, $parameters[1]);
    return $msg;
  }
}
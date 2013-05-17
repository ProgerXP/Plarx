<?php namespace Px;

class View extends \Laravel\View {
  // Unlike default lets view's $data override $shared.
  //= hash
  function data() {
    $data = $this->data + static::$shared;

    foreach ($data as $key => &$value) {
      if ($value instanceof \Laravel\View or $value instanceof \Laravel\Response) {
        $value = $value->render();
      }
    }

    return $data;
  }

  // function (array $keyValues)
  // function (str $key, $value)
  // As with() but adds only those keys that don't yet exist.
  function append($key, $value = null) {
    $this->data += is_array($key) ? $key : array($key => $value);
    return $this;
  }
}
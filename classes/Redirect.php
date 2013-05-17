<?php namespace Px;

class Redirect extends \Laravel\Redirect {
  // Unlike default provides $defaultURL parameter used in the absense of referrer.
  //= Redirect
  static function back($defaultURL = '/', $status = 302) {
    $referrer = Request::referrer();
    if (url($referrer) === strtok(\URI::full(), '?')) {
      $referrer = null;
    }

    return static::to($referrer ?: $defaultURL, $status);
  }

  // For compatibility with Px\Response.
  function set($content) {
    return $this;
  }
}
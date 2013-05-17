<?php namespace Px;

class Request extends \Laravel\Request {
  // Used by local(); if set overrides default detection.
  //= null use default detection (non-'local' LARAVEL_ENV), bool override it
  static $local;

  //= bool indicating if current request occurs in a safe (e.g. deevelopment) environment
  static function local() {
    return isset(static::$local) ? static::$local : static::is_env('local');
  }

  // function ()
  // Indicates if current request was made via AJAX or is a regular web request.
  // Unlike default before checking X-Requested-With header it looks for (in this
  // order) '_ajax' GET and POST variables, 'post_ajax' cookie (if current request
  // is not a GET) and, finally, 'ajax' cookie.
  //= bool if performed via AJAX, str 'd' if forced debug AJAX view via web
  //
  // function ($override)
  //* $override null depend on request's value, bool value to override, str 'd'
  //= $override
  static function ajax($override = null) {
    static $overriden;

    if (func_num_args() > 0) {
      return $overriden = $override;
    }

    $force = $overriden;

    if (!isset($force)) {
      $force = array_get($_GET, '_ajax', array_get($_POST, '_ajax'));
    }

    if (!isset($force) and static::method() !== 'GET') {
      $force = array_get($_COOKIE, 'post_ajax');
    }

    isset($force) or $force = array_get($_COOKIE, 'ajax');

    if (isset($force)) {
      return $force === 'd' ? $force : !!$force;
    } else {
      return parent::ajax();
    }
  }

  // Parses Accept-Language header and returns most suitable language the client accepts.
  //* $accepts str Accept-Language value, null gets it from Request
  //* $all array of str, null 'application.all_languages' config value - members are
  //  2-charactered strings of ISO-639-1 language codes (e.g. 'ru').
  //= str 2-char code, null if no language from $all is acceptable for $accepts
  //
  //? detectLanguage('ru-ru,ru;q=0.8,en-us', array('en', 'ru', 'ja'))   //=> ru
  static function detectLanguage($accepts = null, array $all = null) {
    $accepts or $accepts = static::server('HTTP_ACCEPT_LANGUAGE');
    $all === null and $all = \Laravel\Config::get('application.all_languages');

    $languages = array();

    foreach (explode(',', $accepts) as $piece) {
      @list($lang, $quality) = explode(';', "$piece;");

      if (@$quality and substr($quality, 0, 2) == 'q=') {
        $quality = floatval(trim( substr($quality, 2) ));
      } else {
        $quality = 1.0;
      }

      $languages[ round($quality * 1000) ] = trim($lang);  // ksort() discards floats.
    }

    krsort($languages);

    foreach ($languages as $lang) {
      $lang = substr($lang, 0, 2);
      if (in_array($lang, $all)) { return $lang; }
    }
  }
}
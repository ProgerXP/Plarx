<?php namespace Px;

class Response extends \Laravel\Response {
  // Uses JsonResponse if current request was made via AJAX.
  //= Response, JsonResponse
  static function adapt($data, $code = 200, $headers = array()) {
    if (Request::ajax()) {
      return JsonResponse::adapt($data, $code, $headers);
    } else {
      return static::make($data, $code, $headers);
    }
  }

  // Uses JsonResponse if current request was made via AJAX.
  //= Response, JsonResponse
  static function adaptError($code = 400, $data = array()) {
    return static::adaptErrorOf('', $code, $data);
  }

  //= Response, JsonResponse
  static function adaptErrorOf($prefix, $code = 400, $data = array()) {
    if (Request::ajax()) {
      return JsonResponse::adaptError($code, $data);
    } else {
      return static::errorOf($prefix, $code, $data);
    }
  }

  // Unlike default uses a generic 'error' view passing it $code and $data
  // if there's no 'error.$code' view.
  //= Response with a View
  static function error($code = 400, $data = array()) {
    return static::errorOf('', $code, $data);
  }

  // Creates an error view with given prefix (such as 'mybundle::').
  //= Response with a View
  static function errorOf($prefix, $code = 400, $data = array()) {
    if (!View::exists($name = $prefix."error.$code")) {
      $name = 'error';
      if ($prefix and View::exists($prefix.'error')) {
        $name = $prefix.$name;
      }
    }

    $data = compact('code') + $data;
    return new static(View::make($name, $data), $code);
  }

  // Returns HTTP status text, e.g. for $code = 404 returns "Not Found".
  //= str, mixed $default
  static function statusText($code, $default = null) {
    return array_get(\Symfony\Component\HttpFoundation\Response::$statusTexts, $code, $default);
  }

  // Unlike default uses overriden Str::toJSON() to encode the response.
  //= Request
  static function json($data, $status = 200, $headers = array(), $jsonFlags = 0) {
    $charset = \Config::get('application.encoding', 'utf-8');
    $headers['Content-Type'] = 'application/json; charset='.$charset;

    return new static(Str::toJSON($data, $jsonFlags), $status, $headers);
  }

  // Unlike default uses overriden Str::toJSON() to encode the response.
  //* $data Eloquent, array of Eloquent
  //= Request
  static function eloquent($data, $status = 200, $headers = array(), $jsonFlags = 0) {
    is_array($data) and $data = func('to_array', $data);
    return static::json($data, $status, $headers, $jsonFlags);
  }

  // Wraps data into a JSONP(added) string ready to be executed.
  //= str
  //
  //? wrapJSONP('cbs[133]', array('a' => 'b'))
  //      //=> try { window.top.cbs[133]( {'a':'b'} ) } catch (e) { }
  static function wrapJSONP($callback, $data, $jsonFlags = 0) {
    $data = Str::toJSON($data, $jsonFlags);
    return "try{window.top.$callback($data)}catch(e){}";
  }

  //= Response
  static function postprocess(\Laravel\Response $response) {
    if (Request::ajax() === 'd') {
      $response = static::make(static::ajaxDebugView($response));
    }

    return $response;
  }

  // Creates 'ajax' view filled with data about (presumably JSON) $response.
  //= View
  static function ajaxDebugView(\Laravel\Response $response) {
    $data = json_decode($response->content, true);
    isset($data) or $data = $response->content;

    $view = View::exists('ajax') ? 'ajax' : 'plarx::ajax';
    return View::make($view, array(
      'application'       => \Config::get('application.name'),
      'status'            => $response->status(),
      'headers'           => $response->headers()->all(),
      'data'              => HLEx::visualize($data),
      'raw'               => $response->content,
      'input'             => HLEx::visualize(Input::all()),
    ));
  }

  // Unlike default properly handles non-Latin (UTF-8) $name avoiding Symfony's
  // exception "The filename fallback must only contain ASCII characters".
  function disposition($file) {
    $type = \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT;
    return $this->foundation->headers->makeDisposition($type, $file, Str::ascii($file));
  }

  function set($content) {
    $this->content = $content;
    return $this;
  }

  // Unlike default compresses the output with ob_gzhandler for JSON response.
  //= null value of Laravel\Response::send()
  function send() {
    if (starts_with($this->headers()->get('Content-Type'), 'application/json') and
        !Request::local()) {
      ob_start('ob_gzhandler');
    }

    return parent::send();
  }
}
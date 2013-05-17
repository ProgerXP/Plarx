<?php namespace Px;

// Separate class for handling JSON responses.
class JsonResponse extends Response {
  // Makes a JSON object of form {code: $code, http: 'Status of $code'} + $data items.
  //* $data array, mixed becomes response => $data
  //* $code int - HTTP response code
  //* $headers hash - additional HTTP headers to attach to the response
  //= JsonResponse
  //
  //? adapt('Oops!', 401, array('X-Status' => 'Oops!'))
  //      //=> {'response': 'Oops!', 'code': 401, 'http': 'Unauthorized'}
  //? adapt(array('details' => 'Log in.', 'url' => '...'), 401)
  //      //=> {'details': 'Log in.', 'url': '...', 'code': 401, 'http': 'Unauthorized'}
  static function adapt($data, $code = 200, $headers = array()) {
    is_array($data) or $data = array('response' => $data);

    $data += compact('code');
    $http = static::statusText($code) and $data += compact('http');

    $callback = (string) Request::ajax();

    if (ltrim($callback, '0..9') !== '' and $callback !== 'd') {
      return static::jsonp($callback, $data, $code, $headers);
    } else {
      return static::json($data, $code, $headers);
    }
  }

  // See JsonResponse::adapt() for the details on parameters.
  //= JsonResponse
  static function adaptError($code = 400, $data = array()) {
    return static::adapt($data, $code);
  }

  static function jsonp($callback, $data, $status = 200, $headers = array(), $jsonFlags = 0) {
    $charset = \Config::get('application.encoding', 'utf-8');
    $headers['Content-Type'] = 'application/javascript; charset='.$charset;

    $data = static::wrapJSONP($callback, $data, $jsonFlags);
    return new static($data, $status, $headers);
  }

  function set($content, $jsonFlags = 0) {
    $callback = (string) Request::ajax();

    if (ltrim($callback, '0..9') !== '' and $callback !== 'd') {
      $this->content = static::wrapJSONP($callback, $content, $jsonFlags);
    } else {
      $this->content = Str::toJSON($content, $jsonFlags);
    }

    return $this;
  }
}
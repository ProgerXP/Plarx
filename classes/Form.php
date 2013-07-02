<?php namespace Px;

class Form extends \Laravel\Form {
  // function ($action, [$errors = array(),] array $fields)
  // Outputs a tabular form with given set of inputs and optional error messages.
  //= null
  static function quick($action, $errors, $fields = null) {
    if (!isset($fields)) {
      $fields = $errors;
      $errors = array();
    }

    return static::quickEx($action, $fields, compact('errors'));
  }

  // Outputs a tabular form with given inputs and optional errors, hiddens and footer.
  //* $action str -  of form '[method ][controller@]action';
  //                 if doesn't contain ':' becomes action($action)
  //* $fields array - inputtable fields; keys are names, values are arrays of
  //  attribute => value pairs. If key is numeric it's a text input which name
  //  equals to the value. If value isn't an array it is treated as input type.
  //* $options hash - can have 'errors' (see htmlErrors()), 'hidden' (see hidden()),
  //  'footer' (callable; result is output instead of the submit button).
  //= null
  static function quickEx($action, $fields, $options = array()) {
    is_object($options) and $options = array('footer' => $options);

    $options += array(
      'method'            => null,
      'errors'            => array(),
      'hidden'            => array(),
      'footer'            => array(get_called_class(), 'submit'),
      'old'               => Input::get(),
    );

    extract($options, EXTR_SKIP);

    if (strrchr($action, ' ')) {
      list($method, $action) = explode(' ', $action, 2);
    } else {
      $method or $method = 'post';
    }

    if ($html = static::htmlErrors($errors)) {
      echo $html, '<hr>';
    }

    $url = strrchr($action, ':') === false ? action($action) : $action;
    echo static::open($url, $options['method']);

    foreach ($hidden as $name => $value) {
      echo static::hidden($name, $value);
    }

    foreach ((array) $fields as $name => $attrs) {
      if (is_int($name)) {
        if ($attrs === '!csrf') {
          echo static::token();
          continue;
        }

        $name = $attrs;
        $attrs = 'text';
      }

      is_array($attrs) or $attrs = array('type' => $attrs);
      $attrs += compact('name');

      if (in_array(array_get($attrs, 'type'), array('checkbox', 'radio'))) {
        $attrs += array('value' => 1);
        array_get($old, $name) and $attrs += array('checked' => 'checked');
      } else {
        $attrs += array('value' => array_get($old, $name));
      }

      echo '<p><label>',
             HLEx::strong( ucfirst(strtr($name, '_', ' ')) ),
             ': ',
             HLEx::tag('input', $attrs),
           '</label></p>';
    }

    echo call_user_func($footer), static::close();
  }

  // Outputs an ordered list of errors.
  //* $errors obj Validator, obj Messages, hash of str - key is field name, value
  //  is the error message (HTML is escaped).
  //= str HTML, null if $errors is empty
  static function htmlErrors($errors, $class = 'form-errors') {
    $errors instanceof \Laravel\Validator and $errors = $errors->errors;
    $errors instanceof \Laravel\Messages and $errors = $errors->messages;

    if (is_array($errors) and $errors) {
      $result = '';

      foreach ($errors as $field => $set) {
        $result .= join(array_map(array('HLEx', 'li_q'), $set));
      }

      return HLEx::ol($result, $class);
    }
  }

  //* $action str - of form 'http://...' or 'ctl@[actn][?query...]
  //* $btnAttrs hash, str 'method'
  static function asButton($action, $caption, $btnAttrs = array()) {
    $action = strtok($action, '?');
    $query = strtok(null);
    strrchr($action, ':') or $action = action($action);

    $btnAttrs = arrize($btnAttrs, 'method');

    echo static::open($action, array_get($btnAttrs, 'method', 'get')),
         $query ? HLEx::hiddens($query) : '',
         static::submit($caption, array_except($btnAttrs, 'method')),
         static::close();
  }
}
<?php namespace Px;

class Validator extends \Laravel\Validator {
  // The 'format' rule fills this with detected format names.
  //
  //= hash key is attribute name, value is false (no format matched) or string
  //
  //? array('logo' => 'gif', 'splash' => false)
  public $formats = array();

  // Unlike default has all arguments optional (useful for creating an empty
  // validator for filling with messages later) and auto-converts $attributes to array.
  //
  //= Validator
  static function make($attributes = array(), $rules = array(), $messages = array()) {
    return parent::make((array) $attributes, $rules, (array) $messages);
  }

  // Creates an empty validator with one error message.
  //
  //* $attribute str - attribute name that contains an error
  //* $rule str - rule name that has failed; can be prefixed with 'bundle::' to
  //  set $this->bundle to it (thus using its language file).
  //* $params array, scalar converted to array - error details for $rule
  //
  //= Validator
  static function withError($attribute, $rule, $params = array()) {
    list($bundle, $rule) = \Bundle::parse($rule);

    $valid = static::make();
    $valid->bundle = $bundle;
    $valid->error($attribute, $rule, $params);

    return $valid;
  }

  // Unlike default ignores (always passes) empty $rule.
  protected function check($attribute, $rule) {
    trim($rule) === '' or parent::check($attribute, $rule);
  }

  // Unlike default $params are optional and the method is public to allow
  // creation of custom validation messages for a controller's response.
  //
  //= true for convenient usage in rules' functions' return's.
  function error($attribute, $rule, $params = array()) {
    $this->errors or $this->errors = new \Laravel\Messages;
    parent::error($attribute, $rule, (array) $params);
    // convenient inside validation methods - if they return !true Validator will
    // add error for them but we add our own here so there's no need to do so.
    return true;
  }

  // Unlike default replaces $message with values from $params (':key' -> value).
  //
  //= str formatted message
  protected function replace($message, $attribute, $rule, $params) {
    $message = parent::replace($message, $attribute, $rule, $params);

    if (!method_exists($this, "replace_$rule")) {
      $from = array_map(function ($s) { return ":$s"; }, array_keys($params));
      $message = str_replace($from, $params, $message);
    }

    return $message;
  }

  // Passes strings that start with a letter or underscore and optionally continue
  // with letters, underscores or digits.
  protected function validate_identifier($attribute, $value) {
    return preg_match('/^[a-z_]\w*$/i', $value);
  }

  // Passes strings that are strtotime()'able. Takes one optional parameter 'stamp'
  // to additionally pass proper numeric values.
  protected function validate_datetime($attribute, $value, $params = array()) {
    return "$value" === '' or
           ($params[0] === 'stamp' and ltrim($value, '0..9') === '') or
           strtotime($value) > 0;
  }

  // The opposite of 'match' rule.
  protected function validate_not_match($attribute, $value, $params = array()) {
    if ($this->validate_match($attribute, $value, $params)) {
      return $this->error($attribute, 'match');
    } else {
      return true;
    }
  }

  // Passes files that have been successfully and fully uploaded.
  protected function validate_upload($attribute, $upload) {
    return is_array($upload) and is_uploaded_file($upload['tmp_name']) and
           !$upload['error'];
  }

  // Passes files that were uploaded fine and are images. Takes two parameters:
  // image:[MIN],[MAX] - MIN and MAX are of form WxH (or WXH, or W*H) and test
  // for image dimensions (inclusively), if set.
  //
  //? image:,300x100 - image width must be <= 300 pixels and height <= 100
  //? image:10*20,100x100 - 10 <= width <= 100, 20 <= height <= 100
  protected function validate_image($attribute, $upload, $params = array()) {
    if (!$this->validate_upload($attribute, $upload)) {
      return $this->error($attribute, 'upload');
    }

    @list($width, $height) = getimagesize($upload['tmp_name']);
    @list($min, $max) = $params;

    if (!$width or !$height) {
      return $this->error($attribute, 'image.unrecognized');
    } else {
      $min and $this->checkImageSize($attribute, $min, true, $width, $height);
      $max and $this->checkImageSize($attribute, $max, false, $width, $height);
      return true;
    }
  }

  // Used by validate_image().
  protected function checkImageSize($attribute, $size, $mustLarger, $imWidth, $imHeight) {
    @list($relWidth, $relHeight) = explode('x', strtr($size, '*X', 'xx'), 2);

    foreach (array('Width', 'Height') as $dimension) {
      if (${"rel$dimension"} != ${"im$dimension"} and
          !((${"rel$dimension"} > ${"im$dimension"}) ^ $mustLarger)) {
        $relation = $mustLarger ? 'small' : 'large';
        $this->error($attribute, "image.{$relation}_".lcfirst($dimension),
                     array('size' => ${"rel$dimension"}));
      }
    }
  }

  // Passes images of given formats only. Before this rule it's recommended
  // to place either 'upload' or 'image' rules. Tests actual file format, not
  // uploaded file's extension. Requires GD extension. Sets $this->formats[$attribute]
  // to the matched format string or false (also producing an error).
  //
  //? format:jpg,png - image must be either JPEG or PNG
  protected function validate_format($attribute, $upload, $formats) {
    if ($this->validate_upload($attribute, $upload)) {
      foreach ((array) $formats as $format) {
        if ($this->checkImageFormat($attribute, $format, $upload['tmp_name'])) {
          $this->formats[$attribute] = $format;
          return true;
        }
      }
    }

    $this->formats[$attribute] = false;

    $formats = strtoupper(join(', ', $formats));
    return $this->error($attribute, 'image.format', compact('formats'));
  }

  // Used by validate_format().
  protected function checkImageFormat($attribute, $format, $file) {
    $format = strtolower($format);
    $func = 'imagecreatefrom'.($format === 'jpg' ? 'jpeg' : $format);

    if (!function_exists($func)) {
      $format = strtoupper($format);
      $this->error($attribute, "image.func", compact('format', 'func'));
    } elseif ($im = @$func($file)) {
      imagedestroy($im);
      return true;
    }
  }
}
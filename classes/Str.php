<?php namespace Px;

class Str extends \Laravel\Str {
  static $symbols = '/[:;<=>!?@"\'\/\\\\#\$&%()*+,\-.^=_`{|}~\[\]]/';

  // Default settings used by number().
  static $defaultNumber = array(
    'html'              => false,
    'decimals'          => 2,
    'point'             => '.',
    'thousands'         => ' ',
  );

  // Throws EJSON upon error. Unlike json_encode() unescapes Unicode sequences (\uXXXX).
  //= str JSON
  static function toJSON($data, $options = 0) {
    if (defined('JSON_UNESCAPED_UNICODE')) {
      $result = json_encode($data, JSON_UNESCAPED_UNICODE | $options);
    } else {
      $result = json_encode($data, $options);
      $result = str_replace('\\\\u', '\\u', addcslashes($result, '\\"'));
      $result = addcslashes(json_decode('"'.$result.'"'), "\r\n");
    }

    if ($error = json_last_error()) {
      throw new EJSON('Cannot encode a JSON object', $error);
    } else {
      return $result;
    }
  }

  // Throws EJSON upon error.
  //= mixed decoded value
  static function fromJSON($data) {
    $data = json_decode($data, true);

    if ($error = json_last_error()) {
      throw new EJSON('Cannot decode a JSON object', $error);
    } else {
      return $data;
    }
  }

  // Converts short size string (e.g. "1M" or "1 M") to proper integer (1048576).
  //= int
  static function toSize($str) {
    $str = trim($str);

    switch (static::last($str)) {
    case 'G': case 'g':  $str *= 1024;
    case 'M': case 'm':  $str *= 1024;
    case 'K': case 'k':  $str *= 1024;
    }

    return (int) $str;
  }

  // Converts an integer (e.g. 5200) into a size string ("5 K").
  //* $size int
  //* $suffixes array - 1st member is for $size > 1024, 2nd - for 1024*1024, etc.
  //= str
  //
  //? size(10, array('B', 'K'))     //=> 10
  //? size(1025, array('B', 'K'))   //=> 1B
  static function size($size, array $suffixes = null) {
    $suffixes or $suffixes = array(' B', ' K', ' M', ' G');

    foreach ($suffixes as $suffix) {
      if ($size >= 1024) {
        $size /= 1024;
      } else {
        break;
      }
    }

    return ((int) $size).$suffix;
  }

  // Converts array of strings "key v a lue", "k2 val2", ... to associative form.
  //* $params array of str, str exploded with $delimiter - if contains an odd
  //  number of members the last is discarded. Non-integer keys and/or non-string
  //  values are preserved while others are split with a space and used as
  //  trim(key) => trim(value).
  //* $delimiter str - only used if $params is string to split it into an array.
  //= hash of str
  //
  //? namify('key value|item #2')     //=> array('key' => 'value', 'item' => '#2')
  //? namify(array('pre' => 'serve', 5 => array(), 3 => 'key   w/ value'))
  //?     //=> array('pre' => 'serve', 5 => array(), 'key' => 'w/ value')
  static function namify($params, $delimiter = '|') {
    if (!is_array($params)) {
      $params = trim($params);
      $params = $params === '' ? array() : explode($delimiter, $params);
    }

    $result = array();

    foreach ($params as $key => $value) {
      if (is_int($key) and is_string($value)) {
        $key = trim($value);

        if (strrchr($key, ' ')) {
          list($key, $value) = explode(' ', $key, 2);
          $value = ltrim($value);
        } else {
          $value = null;
        }
      }

      $result[$key] = $value;
    }

    return $result;
  }

  // Generates a random password string.
  //* $options hash, int length - specifies length, number of digits, etc.
  //= str
  static function password($options = array()) {
    is_array($options) or $options = array('length' => $options);
    $options += array('length' => 6, 'capitals' => 1, 'digits' => 1, 'symbols' => 0);
    extract($options, EXTR_SKIP);

    $generate = function (&$charsToAdd, $fromCharCode, $toCharCode)
                          use (&$password, $length) {
      for (; $charsToAdd > 0 and strlen($password) < $length; --$charsToAdd) {
        $password .= chr( mt_rand($fromCharCode, $toCharCode) );
      }
    };

    $password = '';

      $generate( $capitals, ord('A'), ord('Z') );
      $generate( $digits,   ord('0'), ord('9') );
      $generate( $symbols,  ord('!'), ord('/') );
      $generate( $length,   ord('a'), ord('z') );

    return str_shuffle($password);
  }

  // Calculates password strength based on how many upper-case letters, digits
  // and symbols it has. If it contains neither of these 0 is returned.
  //= int 0+
  static function strength($str, $symbolFactor = 2) {
    return preg_match_all('/[A-Z]/', $str, $matches) +
           preg_match_all('/[0-9]/', $str, $matches) +
           preg_match_all(static::$symbols, $str, $matches) * $symbolFactor;
  }

  //= str normalized IP string, null if $str isn't a well-formed IP address
  static function ip($str) {
    $ip = ip2long($str);
    return is_int($ip) ? long2ip($ip) : null;
  }

  // Replaces in $str each ":KEY" of $replaces with the corresponding value.
  // If $replaces is an object with to_array() it's called. If the value is still
  // not an array it's casted to string.  If $replaces is a string, or has been
  // converted to one, it becomes an array with key 0.
  //= str
  static function format($str, $replaces) {
    if (is_object($replaces) and method_exists($replaces, 'to_array')) {
      $replaces = $replaces->to_array();
    }

    if (is_object($replaces)) {
      $replaces = "$replaces";
    } elseif (!is_array($replaces)) {
      $replaces = (array) $replaces;
    }

    $from = $to = array();

    foreach ($replaces as $fromStr => $toStr) {
      if ($toStr === null or is_scalar($toStr)) {
        $from[] = ":$fromStr";
        $to[] = $toStr;
      }
    }

    return str_replace($from, $to, $str);
  }

  //= str datetime in MySQL format: "2012-11-13 22:51:29"
  static function sqlDateTime($time) {
    return date('Y-m-d H:i:s', $time);
  }

  // Formatts an integer/float as "123 456.78" with at most 2 decimals (trailing
  // zeros are removed). In HTML mode uses non-breaking space. See also HLEx::number().
  //= str
  static function number($num, $options = null) {
    static $utf8Nbsp;
    $utf8Nbsp or $utf8Nbsp = utf8_encode("\xA0");

    $options = arrize($options, 'html') + static::$defaultNumber;
    $point = $options['point'];

    $num = number_format($num, $options['decimals'], $point, $options['thousands']);
    strrchr($num, $point) and $num = rtrim(rtrim($num, '0'), $point);
    $options['html'] and $num = str_replace(' ', $utf8Nbsp, $num);

    return $num;
  }

  // Formats a number according to localization rules, i.e. using '-s' in English.
  //* $strings string becomes array, array - the 1st member is sentense (with '$'
  //  becoming the formatted string), the 2nd member specifies format rules in form:
  //  "stem, 0, 1, 2-4, 5-20 [*]" - where stem may contain '$' (replaced with
  //  $number itself) and '%' (replaced with the inflection, if none present it's
  //  appended to the stem); if '*' is present then values greater than 20 are
  //  reduced to it (23 becomes 3, etc.) - as it's the case in Russian, otherwise
  //  the last inflection is used (as in English - both 2 and 222 have -s).
  //* $number int, float
  //* $html bool - if set the number is wrapped into a span to allow for styling.
  //= str
  //
  //? langNum(array('$ today', '$ hit, s, , s, s'), 1)    //=> 1 hit today
  //? langNum(array('$', '$ hit, s, , s, s'), 1)          //=> 1 hit
  //? langNum('$ hit, s, , s, s', 2)    //=> 2 hits
  //? langNum('h$t, s, , s, s', 0)      //=> h0ts
  //? langNum('hit with -%', 153)       //=> hit with -s
  //? langNum('$ hit, s, , s, s*', 21)  //=> 21 hit (because of '*')
  //
  static function langNum($strings, $number, $html = false) {
    is_object($strings) and $strings = "$strings";
    $strings = array_values((array) $strings);
    isset($strings[1]) or $strings = array('$', $strings[0]);
    list($sentence, $numLang) = $strings;

    $rolls = substr($numLang, -1) === '*';
    $infl = explode( ',', rtrim($numLang, '*') );  // $ хит, ов, , а, ов*
    foreach ($infl as &$str) { $str = trim($str); }

    $stem = array_shift($infl);
    $word = static::inflectNum($stem, $infl, $rolls, $number);

    $number = static::number($number, $html);
    $html and $number = "<span class=\"num\">$number</span>";

    return preg_replace('/\\$/u', preg_replace('/\\$/u', $number, $word), $sentence);
  }

    // Part of langNum() formatter, performs numeric inflection.
    //= str $stem with appended inflection or '%' occurrence(s) replaced with it
    static function inflectNum($stem, $inflections, $numberRolls, $number) {
      $inflection = '';

      if ($number == 0) {
        $inflection = $inflections[0];
      } elseif ($number == 1) {
        $inflection = $inflections[1];
      } elseif ($number <= 4) {
        $inflection = $inflections[2];
      } elseif ($number <= 20 or !$numberRolls) {
        $inflection = $inflections[3];
      } else {  // 21 and over
        return static::inflectNum( $stem, $inflections, $numberRolls, substr($number, 1) );
      }

      $word = preg_replace('/%/u', $inflection, $stem, -1, $count);
      return $count > 0 ? $word : $stem.$inflection;
    }
}
<?php namespace Px;
/*
  Part of Plarx | https://github.com/ProgerXP/Plarx
*/

// Enchances Blade view preprocessor with:
// * overriden @include that uses Px\View instead of Laravel\View
// * overriden @yield('section') that will try to output Section with given name
//   and if it is undefined will output view variable $section instead
class Blade extends \Laravel\Blade {
  //= array of str compiler name (method: "compile_OVERRIDEN")
  static $overriden = array('includes', 'yields');

  // Overrides some default Blade compilers.
  static function sharpen() {
    $list = &static::$compilers;
    $list = array_diff($list, array_merge(static::$overriden, array('extensions')));
    $list[] = 'extensions';

    foreach (array('includes', 'yields') as $ext) {
      $func = "compile_$ext";
      static::extend(function ($value) use ($func) { return Blade::$func($value); });
    }

    parent::sharpen();
  }

  // Unlike default uses overriden View class instead of Laravel\View.
  static function compile_includes($value) {
    $pattern = static::matcher('include');
    $php = '$1<?php echo View::make$2->with(get_defined_vars())->render()?>';
    return preg_replace($pattern, $php, $value);
  }

  // Unlike default uses $value variable if section with this name is undefined.
  //
  // This allows for more flexibility - for example, page content can be given
  // both as a variable (like View::make()->with('content', '...')) and as a section
  // (mostly useful for templates that do @layout and @section('content')).
  static function compile_yields($value) {
    $pattern = static::matcher('yield');
    $php = '$1<?php echo Section::has$2 ? Section::yield$2 : ${$2}?>';
    return preg_replace($pattern, $php, $value);
  }
}
<?php namespace Px;

class View extends \Laravel\View {
  static $overlayHooked = false;

  //= array of hash - see overlay().
  static $overlays = array();

  //= int     times to skip view constructions
  //= true    don't overlay views
  //= mixed   normally overlay defined views
  static $skipOverlay;

  // Adds a redirection of views located under given $src to $dest (if target
  // exists, otherwise uses original view in $src). Calling overlay() on the same
  // $src twice will override previous call. Paths inside registered $dest's are
  // never overlaid to ignore recursion.
  //
  //* $src str - of regular [bundle::][path...]path format
  //* $dest str - as $src.
  static function overlay($src, $dest) {
    if (!static::$overlayHooked) {
      Event::preview(static::loader, array(get_called_class(), 'getOverlaid'));
      static::$overlayHooked = true;
    }

    list($bundle, $element) = \Bundle::parse($src);
    $vars = compact('src', 'dest', 'bundle', 'element');
    static::$overlays[$src] = array_map('strval', $vars);
  }

  static function getOverlaid($bundle, $view) {
    if (static::$skipOverlay === true or
        (is_int(static::$skipOverlay) and --static::$skipOverlay >= 0)) {
      return;
    }

    foreach (static::$overlays as $overlay) {
      if (static::isWithin(array($bundle, $view), $overlay['dest']) !== null) {
        // bypass to other templaters not recusring into overlaid views.
        return;
      }
    }

    foreach (static::$overlays as $overlay) {
      $tail = static::isWithin(array($bundle, $view), $overlay);

      if ($tail !== null) {
        list($overBundle, $overView) = \Bundle::parse($overlay['dest']);
        $path = Event::until(static::loader, array($overBundle, $overView.$tail));

        if ($path !== null) {
          \Log::info("Overlay: $bundle::$view -> [$overBundle::$overView]$tail");
          return $path;
        }
      }
    }
  }

  static function isWithin($view, $location) {
    $parse = function ($view) {
      is_array($view) or $view = \Bundle::parse($view);
      isset($view[0]) or $view = array($view['bundle'], $view['element']);
      return $view;
    };

    $view = $parse($view);
    $location = $parse($location);

    if (($location[0] === '*' or $location[0] === $view[0]) and
        ($location[1] === '' or starts_with($view[1], $location[1]))) {
      return substr($view[1], strlen($location[1]));
    }
  }

  // Ignores overlay rules constructing original view.
  //
  //= View
  static function underlaid($view, array $vars = array()) {
    static::$skipOverlay = 1;
    return new View($view, $vars);
  }

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
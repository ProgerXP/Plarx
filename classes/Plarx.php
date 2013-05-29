<?php namespace Px;

use Laravel\Config;

class Plarx {
  static $dropBuffer = true;

  static $fixRandom = true;

  static $listen = true;

  static $supersede = false;

  static $startApp = false;

  // Enabling this will terminate for redirection requests that haveno language slug.
  static $localize = false;
  static $permanentLangRedir = true;

  static $sqlLog = false;     //= str file name like 'c:\\mylara.sql'
  static $firstSQL = true;
  static $sqlLogLimit = 25;   //= float, int kilobytes

  // Initializes various Plarx extensions.
  static function init(&$options = null) {
    if ($options === false) { return; }

    foreach ((array) $options as $name => $value) {
      isset(static::$$name) and static::$$name = $value;
    }

    static::$listen and static::listen();

    if (static::$dropBuffer) {
      // Web Laravel starts with mb_output_handler which is useless most of the time.
      if (ob_get_level()) {
        if (!Request::local()) {
          ob_end_clean();
        } elseif (trim($buffer = ob_get_clean()) !== '') {
          // echo'ing or ob_end_flush()'ing empty buffer will send the headers.
          echo $buffer;
        }
      }

      Request::cli() or ob_start();
    }

    if (static::$fixRandom and !Request::local()) {
      // random generator attack prevention: http://habrahabr.ru/company/pt/blog/149746/
      $seed = unpack('la/lb', openssl_random_pseudo_bytes(8));
      srand(reset($seed));
      mt_srand(next($seed));
    }

    static::$supersede and static::supersede();
    static::$startApp and static::startApp();
    static::$localize and static::localize();
  }

  // Attaches common event listeners.
  static function listen() {
    Event::listen(Config::loader, function ($bundle, $file) {
      return Config::file($bundle, $file);
    });

    Event::listen(\Laravel\Lang::loader, function ($bundle, $language, $file) {
      return \Lang::file($bundle, $language, $file);
    });

    Event::listen(\Laravel\View::loader, function ($bundle, $view) {
      return View::file($bundle, $view, \Bundle::path($bundle).'views');
    });

    Event::listen('laravel.query', array(get_called_class(), 'logSQL'));
  }

  // Implements better globalization URL slug than default Laravel implementation.
  static function localize() {
    $uri = ltrim(\Laravel\URI::current(), '/');
    $languages = (array) Config::get('application.all_languages');
    // disable Laravel's default globalization mechanism.
    Config::set('application.languages', array());

    if (preg_match('~^('.join('|', $languages).')($|/.*)~', $uri, $match)) {
      Config::set('application.language', $match[1]);
      \Laravel\URI::$uri = ltrim($match[2], '/');
      \Laravel\URI::$uri === '' and \Laravel\URI::$uri = '/';
      Config::set('application.asset_url', \Laravel\URL::base());
      \Laravel\URL::$base = \Laravel\URL::base()."/$match[1]";
    } else {
      $language = Request::detectLanguage() ?: array_shift($languages);
      $home = $home = \Laravel\URL::base()."/$language/$uri";

      $status = static::$permanentLangRedir ? '301 Moved Permanently' : '302 Found';
      header("HTTP/1.0 $status");
      header("Status: $status");    // for FastCGI.
      header("Location: $home");

      header('Content-Type: text/html; charset=iso-8859-1');
      $home = htmlspecialchars($home);
      echo '&rarr; <a href="'.$home.'">'.$home.'</a>';
      exit;
    }
  }

  // Puts Plarx classes, cosntants and functions into global namespace.
  // Useful when developing a complete application depending on Plarx.
  static function supersede($namespace = '\\', $skipClasses = array()) {
    static::constantsTo($namespace);
    static::classesTo($namespace, $skipClasses);
    static::functionsTo($namespace);
  }

  // Defines Plarx constants in given namespace ignoring already defined ones.
  static function constantsTo($namespace) {
    $namespace = ltrim(rtrim($namespace, '\\').'\\', '\\');

    foreach (get_defined_constants() as $name => $value) {
      if (substr($name, 0, 3) === __NAMESPACE__.'\\') {
        $name = $namespace.substr($name, 3);
        defined($name) or define($name, $value);
      }
    }
  }

  // Aliases Plarx classes in given namespace ignoring already defined class names.
  //
  //? aliasIn('\\')       // puts Plarx classes into global namespace (DoubleEdge, etc.)
  //? aliasIn('')         // the same
  //? aliasIn('My\\NS')   // puts them into My\NS (My\NS\DoubleEdge, etc.)
  //? aliasIn('\\', 'Str')    // does not introduce Px\Str, uses standard Laravel's
  static function classesTo($namespace, $skip = array()) {
    $namespace = ltrim(rtrim($namespace, '\\').'\\', '\\');
    $skip = array_flip((array) $skip);

    foreach (static::classes() as $short => $full) {
      isset($skip[$short]) or \Laravel\Autoloader::alias($full, $namespace.$short);
    }
  }

  // Aliases global Plarx functions in given namespace ignoring already taken names.
  static function functionsTo($namespace, $skip = array()) {
    $namespace = ltrim(rtrim($namespace, '\\').'\\', '\\');
    $skip = array_flip((array) $skip);

    $eval = $namespace ? 'namespace '.substr($namespace, 0, -1).';' : '';
    $functions = get_defined_functions();

    foreach ($functions['user'] as $name) {
      if (substr($name, 0, 3) === 'px\\') {
        $short = substr($name, 3);

        if (!isset($skip[$short]) and !function_exists($short)) {
          $eval .= "function $short(){return call_user_func_array('".
                   "$name',func_get_args());}";
        }
      }
    }

    eval($eval);
  }

  //= hash 'PlarxClass' => 'Px\\PlarxClass'
  static function classes() {
    $result = array();

    foreach (scandir(__DIR__) as $file) {
      if (substr($file, -4) === '.php' and $class = substr($file, 0, -4)) {
        $result[$class] = __NAMESPACE__."\\$class";
      }
    }

    return $result;
  }

  // Executes common code from application/start.php.
  static function startApp() {
    ini_set('display_errors', Request::local() ? 'on' : 'off');
    date_default_timezone_set(Config::get('application.timezone'));

    $aliases = Config::get('application.aliases');
    \Laravel\Autoloader::$aliases +=
      array_diff_key($aliases, array_flip(get_declared_classes()));

    \Autoloader::directories(array(path('app').'models', path('app').'libraries'));

    if (!Request::cli() and Config::get('session.driver') !== '') {
      \Session::load();
    }

    Config::get('application.profiler') and Profiler::attach();

    // extended Blade relies on Plarx' View and Section classes present in global NS.
    static::$supersede ? Blade::sharpen() : \Blade::sharpen();

    \View::share('ok', Request::cli() ? null : \Session::get('ok'));
  }

  // Handler for 'laravel.query' event logging queries to some local file.
  static function logSQL($sql, $bindings, $time) {
    if ($file = static::$sqlLog) {
      if (static::$firstSQL) {
        static::$firstSQL = false;

        if (!is_file($file) or filesize($file) >= static::$sqlLogLimit * 1024) {
          file_put_contents($file, '');
        } else {
          file_put_contents($file, "\n", FILE_APPEND);
        }
      }

      foreach ($bindings as $binding) {
        $sql = preg_replace('/\?/', \DB::connection()->pdo->quote($binding), $sql, 1);
      }

      $connection = \DB::connection();

      if (\property_exists($connection, 'pdo') and \is_object($connection->pdo) and
          $id = $connection->pdo->lastInsertId()) {
        $sql = "# last insert ID $id\n$sql";
      }

      file_put_contents($file, "$sql\n", FILE_APPEND);
    }
  }
}
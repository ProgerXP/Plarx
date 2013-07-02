<?php namespace Px;
/*
  Part of Plarx | https://github.com/ProgerXP/Plarx
*/

// Enchanced controller class. Among many new features it provides handling of both
// AJAX and regular web requests with minimum code repetitions by creating
// 'ajax_'-actions that can be also invoked from a regular method.
//
//?
//  function post_edit($id) {
//    $result = $this->ajax($id);
//    if ($result instanceof AModel) {
//      return Redirect::to_action('amodel@show', array($result->id));
//    } else {
//      return $result;
//    }
//  }
//
//  function ajax_post_edit($id) {
//    $rules = array('title' => 'required|min:5|max:50');
//    $validator = Validator::make(Input::get(), $rules);
//    if ($validator->fails()) {
//      return $validator;
//    } elseif ($model = AModel::find($id)) {
//      return $model->fill(Input::get())->save() ? $model : 500;
//    } else {
//      return 404;
//    }
//  }
//
class DoubleEdge extends \Laravel\Routing\Controller {
  // List of views to be passed through without wrapping them into ->$fullView.
  // Can be either exact view names or their group (before first period).
  //
  //= array of str
  //
  //? array('error', 'user.login')
  //    // views like 'error.404', 'error', 'error.denied', 'user.login' (but not
  //    // 'user' or 'user.signup') won't be wrapped into $this->fullView, if defined
  //
  static $unwrappedViews = array('error');

  // Input variable name used to disable wrapping of response into ->$fullView
  // even if it doesn't match ->$unwrappedViews. Useful for requesting bare HTML
  // data via AJAX.
  //
  //= str
  //
  //? http://example.com/controller/action?_naked=1
  static $nakedVar = '_naked';

  // Sets default Laravel's Controller's RESTful mode from false to true. Can be
  // safely disabled in children although DoubleEdge is more elegant as RESTful.
  //
  //= bool
  public $restful = true;

  // If set requests to this controller containing numeric value instead of
  // first URL slug (the action name) will be handled by the 'index' action.
  // If not set regular method named get_123() will be called.
  //
  //= false   disable and call VERB_123()
  //= true    call VERB_index() on numeric action
  //= str     name of action to pass control to like 'show'
  //
  //? http://example.com/controller/10
  //    // equivalent to visiting http://example.com/controller/index/10
  public $numericToIndex = false;

  // Often used to refer to action's view like 'return $this->layout->with(...);'.
  //
  // Additionally, in DoubleEdge it's used to wrap a non-View action response -
  // for example, if it's an array it's passed to $this->layout.
  // See ->makeResponse() for details.
  //
  //= str     name of view current action is rendered into; if set before an action
  //          is executed (e.g. as instance property or constructor) named View is
  //          created and can be accessed from the action's method
  //= true    equivalent to setting this to "controller.action"
  //= false   action view is undefined (don't create View object)
  //= View    precreated action view object
  public $layout = true;

  // Additional variables to pass to the layout when ->makeResponse() converts action
  // result into a Laravel Response. For example, if it returns a Validator and this
  // is set to array('menu' => ...) then $this->layout will receive not only $errors
  // (retrieved from Validator) but also $menu.
  //
  // These vars won't override the ones with the same name returned by the action.
  // Also, they are only used for non-AJAX requests (as layout isn't used for AJAX).
  //
  // If action/Closure returns a non-array this result is ignored.
  //
  //= str     call another method to fill in the variables of form METHOD[ ARG[ ...]] -
  //          space-separated list of arguments to pass; if METHOD is 'get', 'post'
  //          or other HTTP verb (in any char case) METHOD is assumed to be
  //          'verb_' + $this->currentAction; if there's no ->$currentAction
  //          nothing is called
  //= Closure ($this, array $currentVars) and expected to return an array of vars
  //= array   variables to pass to ->$layout
  //
  //? 'delete'
  //    //=> $this->delete_[last_action]()
  //? 'ajax_get_list'
  //    //=> $this->ajax_get_list()
  //? function ($self) use ($id) { return $self->get_index($id); }
  //? "get $id"
  //    // equivalent to the above if $currentAction is 'index' - call get_index()
  //    // and pass it one argument (the value of $id)
  //? "get_index $id"
  //    // equivalent to the above but doesn't depend on $currentAction (always 'index')
  //? array('var1' => 'value', 'menu' => array(...))
  //
  public $layoutVars = array();

  // Specifies the view non-AJAX requests should be wrapped into.
  // See also ->$unwrappedViews, ->$nakedVar.
  //
  //= str     view name like 'layouts.full'
  //= false   don't wrap the response
  //= View    precreated view object to wrap response into
  public $fullView = 'full';

  // Variables inherited by ->$fullView from ->$layout used to render action's response.
  // Format: ['respViewVarName' =>] 'fullViewVarName'; if left-side name is omitted
  // both names are the same.
  //
  //= array of str
  //
  //? array('title' => 'winTitle', 'metaDesc')
  //    // sets full view's $winTitle to layout's $title and $metaDesc - to $metaDesc
  public $fullViewVars = array('title');

  // By default DoubleEdge will server response errors (404, 403, 500, etc.) by
  // application-wise or bundle-view "error.CODE" or "error" views. If this is
  // enabled each action's view will be used instead and named variable will be
  // set to numeric response code. Can be set from within action's method.
  //
  //= int     same as array(int)
  //= array of int response codes on which layout will be used
  //= true    uses layout on any response code
  //= Closure ($code, array $data, $self) returns:
  //          - array - variables to pass to $this->layout
  //          - mixed - use default server response view (action-independent)
  //= false   disable per-action views
  public $layoutRescue = false;

  // Name of action being executed. On nested calls to ->response() this always
  // contains the topmost (outermost) action.
  //
  //= str     like 'edit' or 'show'
  //= null    if none or isn't called by ->response()
  public $currentAction;

  // This controller's name like 'admin.login'.
  //
  //= str
  public $name;

  // Lets you avoid accessing Auth::user(). Any value but null overrides Auth.
  // Any value but an Eloquent model results in "unauthorized" state (Auth::user()
  // is still not called). Only works if controller uses ->user() to auth checks.
  //
  //= null      use Auth
  //= Eloquent  use this model as representing current user
  //= false     "unauthorized"
  //= mixed     "unauthorized"
  public $user;

  // Extracts controller name from its class name by removing trailing '_Controller'
  // and replacing underscores (as per PSR-0) with dots.
  // Doesn't handle namespaces and bundles.
  //
  //= str     in lower case like 'admin.login'
  //
  //? nameFrom('Admin_Login_Controller')
  //    //=> 'admin.login'
  //? nameFrom('SomeClass_Name')
  //    //=> 'someclass.name'
  //? nameFrom('Some\\NS\\My_Controller')
  //    //=> 'some\\ns\\my'
  //
  static function nameFrom($class) {
    if (\ends_with($class, $suffix = '_Controller')) {
      $class = substr($class, 0, -1 * strlen($suffix));
    }

    return strtolower(str_replace('_', '.', \class_basename($class)));
  }

  // It's not recommended to override the constructor - use ->init(), ->bindAssets()
  // instead.
  function __construct() {
    parent::__construct();

    $this->init();
    $this->bindAssets();

    $name = static::nameFrom(get_class($this));
    $this->name = \Bundle::identifier($this->bundle, $name);
  }

  // To be overriden; place for filters and other controller settings.
  protected function init() { }

  // To be overriden; place for Asset::add() calls.
  protected function bindAssets() { }

  // Returns name of method used to handle $action name within given request setup.
  // Takes ->$restful into account.
  //
  //* $action str - name of action like 'edit' or 'show'.
  //* $method str   HTTP verb like 'get' or 'put' (in any case),
  //          null  use current request
  //* $ajax   bool  indicates if it should be handled as if for AJAX or not,
  //          null  use current request
  //
  //= str like 'ajax_put_message'
  //
  //? methodName('message', null, true);
  //    //=> 'ajax_post_message' or 'ajax_do_message' for non-RESTful
  //? methodName('message', 'DELETE', false);
  //    //=> 'delete_message' or 'get_message' if there's no 'delete_message' method
  //
  function methodName($action, $method = null, $ajax = null) {
    if ($this->restful) {
      $method === null and $method = Request::method();
      $func = strtolower("{$method}_$action");

      if (strtoupper($method) !== 'GET' and !method_exists($this, $func) and
          method_exists($this, "get_$action")) {
        $func = "get_$action";
      }
    } else {
      $func = "do_$action";
    }

    if ( isset($ajax) ? $ajax : Request::ajax() ) {
      $func = "ajax_$func";
    }

    return $func;
  }

  // Determines if this controller can handle $action name with given request setup.
  // See ->methodName().
  //
  //= bool
  function hasHandler($action, $method = null, $ajax = null) {
    return method_exists($this, $this->methodName($action, $method, $ajax));
  }

  // Calls this controller's action by name and with parameters (usually URL parts).
  // If ->$restful isn't enabled method name is 'do_$action', otherwise 'verb_$action'
  // or if it's undefined - 'get_$action'. 'ajax_' prefix is added later by ->act().
  //
  // Action method can return different value types like strings and arrays - they
  // are handled according to ->makeResponse() rules to become a Response object.
  //
  //* $action str - name without HTTP verb, like 'index', 'edit' or 'show'.
  //  If ->$numericToIndex is enabled numeric $action is forwarded to corresponding
  //  action with this value pushed in front of $params.
  //* $params array, mixed - autoconverted to array.
  //
  //= Response    or its child class like Redirect
  function response($action, $params = array()) {
    $params = arrize($params);

    if ($name = $this->numericToIndex and $action and ltrim($action, '0..9') === '') {
      array_unshift($params, $action);
      $action = $name === true ? 'index' : $name;
    }

    isset($this->currentAction) or $this->currentAction = $action;
    $response = $this->beforeAction($action, $params);

    if (!isset($response)) {
      $response = $this->act($this->methodName($action, null, false), $params);
    }

    $this->afterAction($action, $params, $response);
    $response = $this->makeResponse($response);
    $this->currentAction === $action and $this->currentAction = null;

    return Response::postprocess($response);
  }

  // Converts a value (string, array, Query or other) into a Response object by
  // using ->makeResponse() rules.
  //
  //= Response    or its child class like Redirect
  function toResponse($response) {
    return $this->makeResponse($response, false);
  }

  // Produces a limited set of possible return values from given value (string, array,
  // Query or other). Typically called by ->makeResponse().
  //
  // Convertion rules are as follows:
  //
  // * null     - becomes 404 (Not Found)
  // * false    - if current ->user() is unset (is a guest) becomes 401 (Unauthorized),
  //              otherwise - 403 (Forbidden)
  // * true     - for AJAX request 200 (OK) code is returned, for web - ->$layout
  //              (errors if unassigned)
  // * Validator or Messages
  //            - for AJAX request 400 (Bad Request) code is returned with body set
  //              to JSON-encoded list of messages; for web message list are passed
  //              to ->$layout (errors if unassigned) as 'errors' variable
  // * Query    - fetches all rows and returns 404 (Not Found) if there are none or
  //              converts them to arrays (with to_array() or (array)). For non-AJAX
  //              request ->$layout is returned (errors if unassigned) with the
  //              following variables set:
  //              * ok    = true
  //              * rows  = fetched rows (array of arrays)
  //              * pages = Paginator instance of Query has one assigned
  // * Eloquent - for AJAX request model fields are encoded in JSON and returned;
  //              for web they're passed as variables to ->$layout (errors if
  //              unassigned) with additional 'ok' variable set to true
  // * everything else but valid return types (see below) results in an error being logged
  //              and 500 (Internal Server Error) status being returned
  //
  //= integer     HTTP status
  //= scalar      page content (possibly to be wrapped into ->$fullView)
  //= Viewy       page content (similar to scalar return type)
  //= Response    complete ready to be sent
  //
  protected function makeTypedResponse($response, $internal = true) {
    if ($response instanceof \Laravel\Validator) {
      $response = $response->errors;
    }

    if ($response === null) {
      $response = E_NONE;
    } elseif ($response === false) {
      $response = $this->user(false) ? E_DENIED : E_UNAUTH;
    } elseif ($response === true) {
      if (Request::ajax()) {
        $response = Response::adapt('ok', E_OK);
      } else {
        $response = array();
      }
    } elseif ($response instanceof \Laravel\Messages) {
      if (Request::ajax()) {
        $errors = $response->messages;
        $response = Response::json(compact('errors'), E_INPUT);
      } else {
        $response = array('errors' => $response);
      }
    } elseif ($response instanceof \Laravel\Database\Query or
              $response instanceof \Laravel\Database\Eloquent\Query) {
      if ($response instanceof Query and $paginator = $response->paginator()) {
        $response = $paginator->results;
      } else {
        $response = $response->get();
        $paginator = null;
      }

      if ($response) {
        $arrize = function ($model) {
          if (method_exists($model, 'to_array')) {
            return $model->to_array();  // query produced by a Model.
          } else {
            return (array) $model;      // raw query, e.g. by DB::query().
          }
        };

        if (Request::ajax()) {
          $response = array_map($arrize, $response);
        } else {
          $response = array('ok' => true, 'rows' => $response, 'pages' => $paginator);
        }
      } else {
        $response = E_NONE;
      }
    } elseif ($response instanceof \Laravel\Database\Eloquent\Model) {
      $model = $response;
      $response = $response->to_array();

      if (Request::ajax()) {
        foreach ($response as &$value) {
          if ($value instanceof \DateTime) {
            $value = gmdate('Y-m-d h:i:s', $value->getTimestamp());
          }
        }
      } else {
        $response += array('ok' => true, 'model' => $model);
      }
    }

    if (!is_int($response) and !is_scalar($response) and !is_array($response) and
        ! $response instanceof \Laravel\View and ! $response instanceof \Laravel\Response) {
      $action = $this->name.'@'.$this->currentAction;
      $error = "Invalid controller response type on [$action]: ".
               (is_object($response) ? get_class($response) : gettype($response));
      \Log::error($error);

      $response = $this->errorResponse(E_SERVER);
    }

    return $response;
  }

  // Converts a value (string, array, Query or other) into a Response-compatible
  // object. Calls -> makeTypedResponse() for preliminary convertion (e.g. from null
  // or Query). Then uses the following rules to produce the final Response object:
  //
  // * integer  - treated as an erroneous HTTP status code for which ->errorResponse()
  //              is generated (typically an 'error.CODE' view)
  // * scalar   - usually a string; is treated as raw response (200, OK); if
  //              ->$fullView is set this string is wrapped into it just like others
  // * array    - for AJAX request is JSON-encoded and returned, for web - given
  //              to ->$layout (errors if unassigned) as variables (by with())
  // * View     - eventually most values above boil down to some view (it can also
  //              be returned directly); it's then wrapped into ->$fullView if the
  //              latter is set and ->$nakedVar isn't passed on this request
  // * Response or its descendant
  //            - are passed through without modification
  // * everything else results in an error being logged and 500 (Internal Server Error)
  //              status being returned
  //
  //* $response mixed - value to be converted into Response; typically result of
  //  calling an action.
  //* $internal bool - indicates if convertion is done internally after calling an
  //  action by ->response() or on some other value by ->toResponse().
  //
  //= Response    or its child class like Redirect
  //
  protected function makeResponse($response, $internal = true) {
    $response = $this->makeTypedResponse($response, $internal);

    if (is_int($response)) {
      $response = $this->errorResponse($response);
    } elseif (is_scalar($response)) {
      $response = Response::adapt($response);
    } elseif (is_array($response)) {
      $response = $this->arrayResponse($response);
    }

    if ($response instanceof \Laravel\View) {
      Input::get(static::$nakedVar) or $response = $this->wrapView($response);
      $response = Response::make($response);
    } elseif (! $response instanceof \Laravel\Response) {
      // this whould be unexpected.
      throw new EPlarx("Invalid value type returned by makeTypedResponse().");
    }

    return $response;
  }

  // Makes friendly response to a HTTP error $code. Handles both AJAX and web requests.
  //
  //* $code int - HTTP status like 404 or 501.
  //* $data array, mixed - additional info given to error view as variables;
  //  if not an array becomes array('msg' => $data).
  //
  //= Response
  protected function errorResponse($code = E_SERVER, $data = array()) {
    $data = $this->errorResponseData($data);

    if (Request::ajax()) {
      $ctl = &$data['controller'];
      $data = array_filter($data, 'is_object');
      $ctl and $data['controller'] = isset($ctl->name) ? $ctl->name : get_class($ctl);
    } else {
      $rescue = $this->layoutRescue;
      is_int($rescue) and $rescue = array($rescue);

      if ($rescue instanceof \Closure) {
        $vars = $rescue($code, $data, $self);
        $rescue = is_array($vars);
        $rescue and $data = $vars;
      }

      if ($rescue === true or (is_array($rescue) and in_array($code, $rescue))) {
        return $this->arrayResponse(array('ok' => $code) + $data);
      }
    }

    $prefix = \Bundle::identifier($this->bundle, '');
    return Response::adaptErrorOf($prefix, $code, $data);
  }

  // Populates error page info with more variables related to current request like
  // controller and action names.
  //
  //* $data array, mixed - if not an array becomes array('msg' => $data).
  //
  //= array
  protected function errorResponseData($data = array()) {
    return arrize($data, 'msg') + array(
      'controller'        => $this,
      'action'            => $this->currentAction,
      'dbg'               => $this->name.'@'.@$action,
    );
  }

  // Makes a response from array value (usually returned by an action) - used by
  // ->makeResponse(). By default encodes $data into JSON for AJAX request or
  // passes it as variables for web request.
  //
  //= Response
  protected function arrayResponse(array $data = array()) {
    if (Request::ajax()) {
      return Response::json($data);
    } else {
      $vars = &$this->layoutVars;

      if (is_string($vars)) {
        $method = strtok($vars, ' ');
        $args = explode(' ', strtok(null));

        if (in_array(strtoupper($method), \Router::$methods)) {
          if ($last = $this->currentAction) {
            $method = strtolower($method)."_$last";
          } else {
            \Log::warn("{$this->name()}->\$layoutVars is set to HTTP verb '$method'".
                       " but \$currentAction is empty - cannot determine full action".
                       " name to call and merge variables with.");
            $method = '';
          }
        }

        if ($method) {
          \Log::info("Layout vars: {$this->name}@$method ( )");
          $vars = call_user_func_array(array($this, $method), $args);
        }
      } elseif ($vars instanceof \Closure) {
        $vars = $vars($this, $data);
      }

      $vars = $this->makeTypedResponse($vars);
      is_array($vars) and $data += $vars;
      return $this->reqLayout()->with($data);
    }
  }

  // Implements ->$fullView - wraps action-returned template into general site layout.
  // Returns $view if ->$fullView is disabled. Full view receives rendered $view
  // as 'content' variable and all inherited $view's variables as per ->$fullViewVars
  // property - see ->fullViewVars() for details.
  //
  //= View    either wrapped or original
  protected function wrapView(\Laravel\View $view) {
    if ($full = $this->fullView and $view->view !== $full and
        !in_array(strtok($view->view, '.'), static::$unwrappedViews)) {
      is_object($full) or $full = View::make($full);
      return $full->with($this->fullViewVars($view));
    } else {
      return $view;
    }
  }

  // Makes an array of variables to be passed to ->$fullView - rendered $nested
  // is passed as 'content' along with inherited ->$fullViewVars, if any.
  //
  //= hash    variables to be passed to the full view
  protected function fullViewVars(\Laravel\View $nested) {
    $vars = array('content' => $nested->render());

    foreach ($this->fullViewVars as $inNested => $inFull) {
      is_int($inNested) and $inNested = $inFull;
      isset($nested[$inNested]) and $vars[$inFull] = $nested[$inNested];
    }

    return $vars;
  }

  // Is invoked before calling an action by ->response(). If returns anything but
  // null original action method isn't called and this returned value is used instead
  // (subject to ->makeResponse() and other response value transformations).
  //
  // By default initializes ->$layout with a View instance (if applicable).
  //
  //* $action str   - name of action about to be called.
  //* $params array - array of action parameters (usually URL parts).
  //
  //= mixed     bypass $action; see ->makeResponse()
  //= null      don't skip calling $action
  protected function beforeAction($action, array $params) {
    $this->layout = $this->layout($action);
  }

  // Is invoked after calling an action by ->response(). Has a chance of modifying
  // $response which can be either action's, its filters' or ->beforeAction()'s.
  // Return value is ignored.
  //
  //* $action str   - name of action that was called.
  //* $params array - array of action parameters (usually URL parts).
  protected function afterAction($action, array $params, &$response) { }

  // Calls action method. Unlike ->response() doesn't do any pre/post-processing
  // like calling ->beforeAction(), setting ->$currentAction or determining method
  // name (from HTTP verb and ->$restful, ->$numericToIndex).
  //
  // If current request is AJAX prepends 'ajax_' to $func.
  //
  // Catches ENoInput and ENoAuth exceptions and returns appropriate values (e.g.
  // 400) for processing by ->makeResponse().
  //
  //* $func str - method to be called like 'get_index', 'do_login' or 'put_edit'.
  //* $params array, mixed - method arguments; everything but array is wrapped into
  //  one including null.
  function act($func, $params = array()) {
    if (Request::ajax() and method_exists($this, "ajax_$func")) {
      $func = "ajax_$func";
    }

    \Log::info("Act: {$this->name}@$func ( ".Event::paramStr($params)." )");

    try {
      return call_user_func_array(array($this, $func), arrizeAny($params));
    } catch (ENoInput $e) {
      if (!isset($e->key)) {
        return E_INPUT;
      } elseif (is_object($this->layout)) {
        return Validator::withError($e->key, 'required');
      } else {
        $action = $this->name.'@'.$this->currentAction;
        \Log::warn("No request variable [{$e->key}] given to [$action].");
        return E_INPUT;
      }
    } catch (ENoAuth $e) {
      \Log::warn($e->getMessage());
      // equivalent to E_DENIED or E_UNAUTH - see makeResponse().
      return false;
    }
  }

  // Catch-all method. If $action looks like an action's method name ('do_foo',
  // 'post_bar' or 'ajax_get_index') calls ->catch404(), otherwise errors.
  //
  //* $action str       - undefined method's name.
  //* $parameters array - arguments that were passed to it.
  function __call($action, $parameters) {
    $prefix = $this->restful ? strtolower(join('|', \Router::$methods)) : 'do';
    $ajax = starts_with($action, 'ajax_');
    $ajax and $action = substr($action, 5);

    if (strtok($action, '_') and preg_match("~^(do|$prefix)_.~", $action)) {
      return $this->catch404($ajax, $action, $parameters);
    } else {
      $class = get_class($this);
      throw new EPlarx("DoubleEdge class [$class] has no instance method [$action].");
    }
  }

  // Is called to handle missing controller actions. By default logs a warning and
  // returns 404 (Not Found) ->errorResponse().
  //
  //* $isAJAX bool  - indicates if an AJAX action form was called.
  //* $action str   - missing action's name like 'edit' or 'index'.
  //* $params array - parameters passed to the action (typically URL parts).
  //
  //= mixed   subject to ->makeResponse() convertion rules
  function catch404($isAJAX, $action, array $params) {
    $isAJAX and $action = "ajax_$action";
    \Log::warn("No controller method [{$this->name}@$action] - returning 404.");
    return $this->errorResponse(E_NONE);
  }

  // Redirects user to previous page passed as $inputVar request variable; if there's
  // none - to referrer (HTTP_REFERER); if there's none either - to $default.
  //
  //* $default str  - URL normalized with url().
  //* $inputVar str - request variable name that holds page address to return to.
  //
  //= Redirect
  function back($default = '/', $inputVar = '_back') {
    $input = Input::get($inputVar);
    return $input ? Redirect::to($input) : Redirect::back($default);
  }

  // Calls AJAX handler corresponding to current action. Must be called from within
  // an action's method. Is equivalent of calling ->ajax_ACTION(...) manually.
  //
  //* $params array, mixed - method arguments; autoconverted to array.
  //
  //?
  //  function post_edit($id) {
  //    return $this->ajax($id);
  //    // equivalent:
  //    return $this->ajax_post_edit($id);
  //    // equivalent:
  //    return $this->ajax(array($id));
  //  }
  protected function ajax($params = array()) {
    $trace = debug_backtrace();
    $func = 'ajax_'.$trace[1]['function'];

    is_object($params) and $params = array($params);
    return call_user_func_array(array($this, $func), (array) $params);
  }

  // Creates View object for this controller and $action. Uses rules described in
  // ->$layout to treat ->$layout's value.
  //
  //* $action str, null - action name to create view for, if available.
  //
  //= View
  //= true    if ->$layout is true but $action is null
  //= null    if ->$layout is unset (falsy) or if necessary view doesn't exist
  function layout($action = null) {
    if ($this->layout === true) {
      if (!isset($action)) {
        return true;
      } else {
        $view = $this->name.'.'.$action;
        if (!View::exists($view) and $action === 'index') {
          $view = $this->name;
        }

        if (View::exists($view)) {
          return View::make($view);
        }
      }
    } elseif ($this->layout) {
      $this->layout[0] === '.' and $this->layout = $this->name.$this->layout;
      return parent::layout();
    }
  }

  // Attempts to create this controller's layout View or errors otherwise.
  // Sets ->$layout to created object if it's not a View already. See ->layout().
  //
  // It will also fail in case of non-existing view - this might happen if you
  // write an action method, set ->$layout to true (it's so by default) but forget
  // to create the actual view file.
  //
  //= View
  function reqLayout() {
    is_scalar($this->layout) and $this->layout = $this->layout();

    if (is_object($this->layout)) {
      return $this->layout;
    } else {
      $name = $this->name.'@'.$this->currentAction;
      throw new EPlarx("No default layout specified for [$name].");
    }
  }

  // If this method is used instead of global Auth::user() this controller can
  // work independent of Auth's state. If $must is set it will always either
  // return a User model or throw ENoAuth but no null.
  // If ->$user is null falls back to Auth::user().
  //
  // If serving an action (e.g. by ->response) ENoAuth is caught by ->act() and
  // treated properly with an error page.
  //
  //= null      if user is unauthorized
  //= Eloquent  assumed to represent a user model
  function user($must = true) {
    $user = isset($this->user) ? $this->user : \Auth::user();

    if ($user instanceof \Laravel\Database\Eloquent\Model) {
      return $user;
    } elseif ($must) {
      throw new ENoAuth($this->name);
    }
  }

  /*-----------------------------------------------------------------------
  | EXAMPLES OF ACTION METHOD DEFINITIONS
  |----------------------------------------------------------------------*/

  // $restful:
  //protected function do_XXX(...)
  //protected function ajax_do_XXX(...)

  // non-$restful:
  //protected function put_XXX(...)
  //protected function ajax_put_XXX(...)
}
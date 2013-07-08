<?php namespace Px;

\Route::filter('plarx::csrf', function () {
  if (Request::forged()) {
    \Log::warn("plarx::csrf filter: denying access due to missing/wrong CSRF token.");
    return Response::postprocess(Response::adaptError(E_INPUT));
  }
});

// plarx::perms[:[!]perm[:[!]perm.2[...]]] [|filter:2 [|...]]
//
// Expects that Auth::user() contains method can(str $feature). In case of denial
// redirects user to named route 'login'.
//
// This filter will always deny access for non-authorized users because protected
// controllers usually rely on current user being logged in.
\Route::filter('plarx::perms', function ($feature_1 = null) {
  $features = is_array($feature_1) ? $feature_1 : func_get_args();
  $controller = is_object(end($features)) ? array_pop($features) : null;
  $user = \Auth::user();

  if ($user and !method_exists($user, 'can')) {
    $msg = "plarx::perms filter: object returned by Auth::user()".
           " (".get_class($user).") doesn't have can() method - returning 403.";
    \Log::error($msg);
    $deny = true;
  } elseif (!$user) {
    $name = $controller ? ' '.$controller->name : '';
    \Log::info("plarx::perms filter: controller$name needs authorized user,".
               " denying access for guest.");
    $deny = true;
  } elseif ($features) {
    $toMiss = $toHave = array();

    foreach ($features as $feature) {
      $feature[0] === '!' ? $toMiss[] = substr($feature, 1) : $toHave[] = $feature;
    }

    $having = array_filter($toMiss, array($user, 'can'));
    $missing = array_omit($toHave, array($user, 'can'));

    $reasons = array();
    $having and $reasons[] = "present flag(s): ".join(', ', $having);
    $missing and $reasons[] = "missing permission(s): ".join(', ', $missing);

    if ($reasons) {
      $name = $controller ? ' '.$controller->name : '';
      $msg = "plarx::perms filter: denying access to controller$name for user".
             " {$user->id} due to ".join(' and ', $reasons).'.';
      \Log::info($msg);
      $deny = true;
    }
  }

  if (empty($deny)) {
    return;
  } elseif ($controller instanceof DoubleEdge) {
    return $controller->toResponse(false);
  } elseif ($user) {
    return Response::postprocess(Response::adaptError(E_DENIED));
  } else {
    return Redirect::to(route('login').'?_back='.urlencode(\URI::full()));
  }
});

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow">

    <title>
      AJAX response to {{ Px\Request::method() }}
      {{ $application ? " &mdash; $application" : '' }}
    </title>

    <style type="text/css">
      body { background: #eee; max-width: 700px; margin: 1em auto; }
      h1 { font-size: 1.5em; text-align: center; margin-top: -0.25em; }
      h1 + h2 { text-align: center; margin: 0; }
      h1 a { text-decoration: none; font-family: sans-serif; }
      h1 a:hover { color: maroon; }
      h2 { font-size: 1.1em; margin-top: 1.2em; margin-left: -30px; }
      h2.s2 { color: green; }
      h2.s4 { color: maroon; }
      h2.s5 { color: red; }
      table, table table table { background: white; border-collapse: collapse; }
      table table, table table table table { background: #f4f4f4; margin: 0.5em 0.2em; }
      table, th, td { border: 1px solid #ccc; }
      th { text-align: left; }
      th, td { padding: 0.2em 0.5em; }
      textarea { background: #f4f4f4; padding: 1em 1em 0 1em; border: 0; display: block; width: 100%; height: 9em; }
      hr { margin: 2em -30px; border: 0; border-top: 1px solid silver; }
      hr + p { margin: -1.5em 0 0 0; text-align: center; font-size: 0.8em; }
      hr + p a { color: navy; }
    </style>
  </head>
  <body>
    <?php
      $htmlURL = rtrim(URI::full(), '?&');
      $htmlURL .= (strrchr($htmlURL, '?') ? '&' : '?').'_ajax=0';
    ?>

    <h1>
      AJAX
      <sup>{{ Px\HLEx::a('&times;', $htmlURL) }}</sup>
      response to {{ Px\Request::method() }}
    </h1>

    <h2 class="s{{ substr("$status", 0, 1) }} s{{ $status }}">
      {{ $status }} {{ Px\Response::statusText($status) }}
    </h2>

    <h2>Headers</h2>

    <table>
      @forelse ($headers as $name => $values)
        @foreach ($values as $value)
          <tr class="{{ Px\HLEx::q($name) }}">
            <th>{{ Px\HLEx::q(strtr(Px\Str::classify($name), '_', '-')) }}</th>
            <td>{{ Px\HLEx::q($value) }}</td>
          </tr>
        @endforeach
      @empty
        <em>None.</em>
      @endif
    </table>

    <h2>Data</h2>
    {{ $data }}

    <h2>Raw</h2>
    <textarea onfocus="this.select();">{{ Px\HLEx::q($raw) }}</textarea>

    <h2>Input</h2>
    {{ $input }}

    <hr>
    <p>
      {{ Px\HLEx::a_q($application ?: 'Home', url('/')) }}
    </p>
  </body>
</html>
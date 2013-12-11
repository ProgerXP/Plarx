<?php namespace Px;

class Query extends \Laravel\Database\Eloquent\Query implements \IteratorAggregate {
  //= Paginator set after calling commonList()
  protected $paginator;

  protected function load(&$models, $relationship, $constraints) {
    foreach ($models as $model) { $model->skipNullRelations = true; }
    $result = parent::load($models, $relationship, $constraints);
    foreach ($models as $model) { $model->skipNullRelations = false; }
    return $result;
  }

  function __clone() {
    $this->table = clone $this->table;
  }

  public function getIterator() {
    return new \ArrayIterator($this->get());
  }

  // nlike default calls ->sync() to save original field values.
  function find($id, $columns = array('*')) {
    $model = parent::find($id, $columns);
    $model and $model->sync();
    return $model;
  }

  //= Paginator
  function paginator() {
    return $this->paginator;
  }

  // Uses an alias instead of this query's original table name. Overrides previously
  // assigned alias, if any. Is also useful to start off a query chain which otherwise
  // would need to be started with where() which otherwise causes a PHP error.
  //* $alias str new alias, null restore original table name
  //
  //? Model::where(..)          // won't work
  //? Model::name()->where(..)  // will work
  function name($alias = null) {
    $this->table->from = strtok($this->table->from, ' ');
    $alias and $this->table->from .= " AS $alias";
    return $this;
  }

  // Returns either original table name or alias set with name().
  function tablePrefix() {
    $alias = trim(strrchr($this->table->from, ' '));
    return $alias ? "$alias." : $this->table->from.'.';
  }

  // Returns a SELECT statement built for current driver as it's executed upon
  // get() or other methods. Useful for creating complex nested queries yet without
  // resorting to writing raw SQL.
  //
  // You might also need to ->addBindings() of the nested query.
  //
  //* $queryName null return bare SQL, string wrap in "(SQL) name" - can be ''.
  //= str SQL
  //
  //? sqlSelect()             //=> SELECT * FROM table
  //? sqlSelect('subquery')   //=> (SELECT * FROM table) subquery
  //? sqlSelect('')           //=> (SELECT * FROM table)
  function sqlSelect($queryName = null) {
    $this->table->selects or $this->table->selects[] = '*';
    $sql = $this->table->grammar->select($this->table);
    isset($queryName) and $sql = rtrim("($sql) $queryName");
    return $sql;
  }

  function addBindings($source) {
    if ($source instanceof \Laravel\Database\Eloquent\Query) {
      $source = $source->table;
    }

    if ($source instanceof \Laravel\Database\Query) {
      $source = &$source->bindings;
    }

    if (is_array($source)) {
      $this->table->bindings = array_merge($this->table->bindings, $source);
      return $this;
    } else {
      $msg = 'Wrong argument of type '.gettype(func_get_arg(0)).' given to '.
             __CLASS__.'->'.__FUNCTION__.'() - must be a Query instance or an array.';
      throw new \InvalidArgumentException($msg);
    }
  }

  // function (array $fields)
  // function (str $field, str $field, ...)
  // Unlike default will convert $fields to an array if it's an object and also
  // allow for multiple fields passed without an array.
  //= $this
  //
  //? $query->select(DB::raw('AVG(score)'))   // won't work with default implementation
  function select($field_1) {
    return parent::select( is_array($field_1) ? $field_1 : func_get_args() );
  }

  // function (array $fields)
  // function (str $field, str $field, ...)
  // Adds more columns to be selected. Unlike select() doesn't override current list.
  function alsoSelect($fields) {
    is_array($fields) or $fields = func_get_args();
    $this->select( array_merge((array) $this->table->selects, $fields) );
    return $this;
  }

  // Convenient shortcut for bulk-adding WHEREs with the same $operator.
  //* $columns hash of 'column' => 'value'
  function whereAre(array $columns, $operator = '=') {
    foreach ($columns as $column => $value) {
      $this->where($column, $operator, $value);
    }

    return $this;
  }

  // Convenient shortcut for filtering out mismatching fields. See whereAre().
  function whereAreNot(array $columns) {
    return $this->whereAre($columns, '!=');
  }

  // Unlike default removes duplicated $values potentially resulting in better
  // prepared statements (fixed number of placeholders in "IN (?, ?, ...)").
  //= $this
  function where_in($column, $values, $connector = 'AND', $not = false) {
    $values = array_unique((array) $values);
    return parent::where_in($column, $values, $connector, $not);
  }

  // Unlike default removes duplicated $values. See where_in().
  //= $this
  function where_not_in($column, $values, $connector = 'AND') {
    return $this->where_in($column, $values, 'AND', true);
  }

  // Unlike default removes duplicated $values. See where_in().
  //= $this
  function or_where_in($column, $values) {
    return $this->where_in($column, $values, 'OR');
  }

  // Unlike default removes duplicated $values. See where_in().
  //= $this
  function or_where_not_in($column, $values) {
    return $this->where_not_in($column, $values, 'OR');
  }

  // Unlike default adds created_at record unless already present.
  function insert($values) {
    is_array(reset($values)) or $values = array($values);

    if ($this->model->usesTimestamps()) {
      foreach ($values as &$columns) {
        isset($columns['created_at']) or $columns['created_at'] = new \DateTime;
      }
    }

    return parent::insert($values);
  }

  // Unlike default adds created_at record unless already present.
  function insert_get_id($values, $column = 'id') {
    if ($this->model->usesTimestamps() and !isset($values['created_at'])) {
      $values['created_at'] = new \DateTime;
    }

    return parent::insert_get_id($values, $column);
  }

  /*
    Note: no overriden update() that adds updated_at because it will change
          the logic and the row will be reported as updated even if only its
          updated_at column was changed but any other column could be left as is.
  */

  // Sets constrains on this query to follow general request patter for sorted,
  // filtered, limited and paginated list.
  //
  //? /goods?sort=price&desc=1&page=3&limit=25&filter[created_at]=2-01-2012
  function commonList($options = array()) {
    is_array($options) or $options = array('prefix' => $options);

    $options += array(
      // Prepended to any Input variable name, e.g. "list1_".
      'prefix'            => '',
      // Specific prefix for this query to avoid collisions with other columns
      // having the same name when used in JOINs.
      'tablePrefix'       => $this->tablePrefix(),
      'filter'            => true,
      // Max value for ?limit=X; <= 0 value turns the restriction off.
      'maxLimit'          => 100,
      'paginate'          => true,
    );

    extract($options, EXTR_SKIP);

    if ($filter) {
      $filter === true and $filter = Input::get('filter');
      $this->filterBy($filter, $tablePrefix);
    }

    if ($sort = Input::get("{$prefix}sort") and $this->model->has($sort)) {
      foreach ((array) $sort as $column) {
        $desc = Input::get("{$prefix}desc");
        $this->order_by($tablePrefix.$column, $desc ? 'desc' : 'asc');
      }
    }

    if ($paginate) {
      $limit = Input::get("{$prefix}limit");
      $limit > 0 and $maxLimit > 0 and $limit = min($maxLimit, $limit);

      $page = max(1, (int) Input::get("{$prefix}page"));
      // working around hardcoded 'page' Input var name for standard Paginator.
      $oldPage = Input::get('page');
      Input::merge(compact('page'));
      $this->paginator = $this->paginate($limit);
      $this->paginator->appends( Input::except('page') );
      Input::merge(array('page' => $oldPage));

      // don't call for_page() because paginate() will break - it will attempt to
      // SELECT COUNT(*) FROM table LIMIT X OFFSET Y and fail because of OFFSET <> 0.
      $modelClass = get_class($this->model);
      $this->for_page($page, $modelClass::$per_page);
    }

    return $this;
  }

  // Adds constrains for this query based on $input variables. Uses filter_VAR()
  // methods of the underlying model if they're available, otherwise filtering
  // by simple '='. Input can contain arrays for filtering one column with several rules.
  //* $input null defaults to Input::all(), hash of fields to filter on
  //* $prefix str - specific table prefix to prepend for each column to avoid
  //  collisions in JOINs with columns of the same name.
  //= $this
  //
  //? filterBy(array('price' => '>5', 'currency' => 'EUR'), 'vm_goods.')
  //    // equivalent to ->where('price', '>', 5)->where('currency', '=', 'EUR')
  //? filterBy(array('price' => array('>10', '<30')))
  //    // this can be produced by a query string like: price[]=%3E10&price[]=%3C30
  function filterBy(array $input = null, $tablePrefix = null) {
    isset($input) or $input = Input::all();
    isset($tablePrefix) or $tablePrefix = $this->tablePrefix();

    $model = $this->model;

    foreach ((array) $model::$fields as $field) {
      $values = array();

      if (isset($input[$field])) {
        $values = (array) array_get($input, $field);
      } elseif (isset($input["{$field}_op"])) {
        $vals = (array) $input["{$field}_val"];

        foreach ((array) $input["{$field}_op"] as $i => $op) {
          isset($vals[$i]) and $values[] = $op.$vals[$i];
        }
      }

      foreach ($values as $value) {
        $value = trim($value);

        if ($value !== '' or in_array($field, $model::$verbatimFilter)) {
          if (method_exists($model, $func = "filter_$field")) {
            $model->$func($tablePrefix, $this, $value);
          } else {
            $this->where($tablePrefix.$field, '=', $value);
          }
        }
      }
    }

    return $this;
  }

  // Usually called from a filter_XXX() method to filter numeric columns. See filterBy().
  // Has this form:   [=|!|<|>]int
  //
  //? >50         // above 50
  //? <38.3       // below 38.3
  //? 127         // exactly 127
  //? =127        // the same
  //? !127        // anything but 127
  //? >-127       // above -127
  //? 0xabc       // equals to '0'
  //? abc         // equals to '0'
  function filterInt($field, $value) {
    if (($value = ltrim($value, '=')) !== '') {
      switch ($value[0]) {
      case '>':   $this->where($field, '>', (int) substr($value, 1)); break;
      case '<':   $this->where($field, '<', (int) substr($value, 1)); break;
      case '!':   $this->where($field, '<>', (int) substr($value, 1)); break;
      default:    $this->where($field, '=', (int) $value); break;
      }
    }
  }

  // Usually called from a filter_XXX() method to filter string columns. See filterBy().
  // Has this form:   [=|^|%|?]str
  // Leading/trailing spaces are trimmed when using no/'=' prefix.
  //
  //? ^start      // starts with "start"
  //? %st_rt      // matches LIKE wildcard: "st*rt"
  //? ?st_rt      // contains exact substring "st_rt"
  //? start       // is exactly "start"
  //? =start      // the same
  //? =           // matches empty string
  //? ''          // empty string is not filtered upon
  function filterStr($field, $value) {
    if ("$value" !== '') {
      if ($value[0] === '=') {
        $this->where($field, '=', trim( substr($value, 1) ));
      } else {
        $wrapped = $this->table->grammar->wrap($field);

        switch ($value[0]) {
        case '^':
          $cond = '= 1';
        case '?':
          isset($cond) or $cond = '!= 0';
          $this->raw_where("LOCATE(?, $wrapped) $cond", array($value));
          break;

        case '%':
          $this->raw_where("$wrapped LIKE ?", array($value));
          break;

        default:
          $this->where($field, '=', trim($value));
          break;
        }
      }
    }
  }

  // Usually called from a filter_XXX() method to filter DATETIME columns. See filterBy().
  // Has this form:   [<|>](timestamp|timestring)
  // 'timestring' is processed with strtotime() and the filter is ignored if it fails.
  //
  //? 2-01-2012     // matches exactly 2nd January of 2012
  //? <1325462400   // matches dates before 2nd January of 2012
  //? >2            // matches dates after the 2nd second of 01/01/1970
  //? now           // matches exactly current date and time (like time())
  function filterDate($field, $value) {
    if (($value = ltrim($value, '=')) !== '') {
      if ($mod = strpbrk($value[0], '><') !== false) {
        list($mod, $value) = array($value[0], substr($value, 1));
      } else {
        $mod = '=';
      }

      if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        $timestamp = strtotime($value);
      } else {
        $timestamp = (int) $value;
      }

      if (is_int($timestamp)) {
        $date = with(new \DateTime)->setTimestamp($timestamp);

        if (!strrchr($value, ' ')) {
          // Only date portion, match that day without exact time.
          $field = \DB::raw('DATE('.$this->table->grammar->wrap($field).')');
          $date = strtok($date->format($this->table->grammar->datetime), ' ');
        } else {
          $date = $date->format($this->table->grammar->datetime);
        }

        $this->where($field, $mod, $date);
      }
    }
  }
}
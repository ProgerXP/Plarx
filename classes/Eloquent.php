<?php namespace Px;

class Eloquent extends \Laravel\Database\Eloquent\Model {
  //= array of str accessible field names
  static $fields = array();

  //= array of str filterable field names that accept empty ('') value
  static $verbatimFilter = array();

  public $skipNullRelations = false;

  // function (array $field)
  // function (str $field, str $field, ...)
  // Tells if this model has given field(s).
  //* $field string field name, array of str field names
  //= true if all fields are present in this model, false otherwise
  static function has($field) {
    func_num_args() > 1 and $field = func_get_args();

    if (is_array($field)) {
      return !array_diff($field, static::$fields);
    } else {
      return in_array($field, static::$fields);
    }
  }

  // Unlike default allows filtering by multiple IDs.
  //* $ids scalar becomes array, array of IDs - non-numeric or <= 0 IDs are skipped.
  //* $columns str - list of fieldsto retrieve into the returned models.
  //= array of Eloquent
  static function all($ids = null, $columns = '*') {
    if (!isset($ids)) {
      return parent::all();
    } else {
      $ids = array_filter((array) $ids, function ($id) {
        return $id > 0 and filter_var($id, FILTER_VALIDATE_INT) !== false;
      });

      return $ids ? static::where_in(static::$key, $ids)->get($columns) : array();
    }
  }

  function __get($key) {
    if ($this->skipNullRelations and array_key_exists($key, $this->attributes)) {
      return $this->{"get_{$key}"}();
    } else {
      return parent::__get($key);
    }
  }

  // Unlike default returns our overriden Query instance.
  function query() {
    return new Query($this);
  }

  function idString($title = null) {
    if (!func_num_args()) {
      $title = isset($this->title) ? $this->title :
               (isset($this->name) ? $this->name : '');
    }

    $class = get_class($this);
    $id = $this->id ? "$class #{$this->id}" : "unsaved $class";

    "$title" === '' or $id .= " ($title)";
    return $id;
  }

  // Used in debug/error messages.
  //
  //? throw new Exception("Cannot update $model.");
  //    //=> "Cannot update ModelName #33 (Title here)."
  //
  function __toString() {
    return $this->idString();
  }

  function getTimestampAttribute($attribute) {
    $value = $this->get_attribute($attribute);

    if ($value === null) {
      return $value;
    } elseif (is_object($value)) {
      return $value->getTimestamp();
    } elseif (ltrim($value, '0..9') === '') {
      return (int) $value;
    } else {
      return (int) strtotime($value);
    }
  }

  function setTimestampAttribute($attribute, $value) {
    is_int($value) and $value = new \DateTime("@$value");
    return $this->set_attribute($attribute, $value);
  }

  function filter_created_at($prefix, $query, $value) {
    $query->filterDate($prefix.'created_at', $value);
  }

  //= bool indicating if this model has timestamp fields (created_at and updated_at).
  function usesTimestamps() {
    $class = get_class($this);
    return $class::$timestamps;
  }
}
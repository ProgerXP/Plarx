<?php namespace Px;

// Enchances MySQL schemas (used with migrations, Fluent):
// * columns can be utf8() - adds COLLATE utf8_unicode_ci
// * columns can be inserted after a specific column - adds AFTER `x`
// * unique indexes (via ALTER) can be added with IGNORE to drop duplicating rows
class MySqlSchema extends \Laravel\Database\Schema\Grammars\MySQL {
  function add(\Laravel\Database\Schema\Table $table, \Laravel\Fluent $command) {
    $columns = $this->columns($table);
    $i = -1;

    foreach ($table->columns as $column) {
      $columns[$i] = 'ADD '.$columns[++$i];
      $column->utf8 and $columns[$i] .= ' COLLATE utf8_unicode_ci';
      $column->after and $columns[$i] .= ' AFTER '.$this->wrap($column->after);
    }

    return 'ALTER TABLE '.$this->wrap($table).' '.join(' ', $columns);
  }

  function key(\Laravel\Database\Schema\Table $table, \Laravel\Fluent $command, $type) {
    $keys = $this->columnize($command->columns);
    $name = $command->name;
    $ignore = $command->ignore ? ' IGNORE' : '';

    return "ALTER$ignore TABLE {$this->wrap($table)} ADD $type $name ($keys)";
  }
}
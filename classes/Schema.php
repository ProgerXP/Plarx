<?php namespace Px;

class Schema extends \Laravel\Database\Schema {
  // Unlike default returns our overriden Schema instances for current driver.
  static function grammar(\Laravel\Database\Connection $connection) {
    if ($connection->driver() === 'mysql') {
      return new MySqlSchema($connection);
    }
  }

  protected function unsigned(\Laravel\Database\Schema\Table $table, \Laravel\Fluent $column) {
    if ($column->unsigned or $column->increment) {
      return ' UNSIGNED';
    }
  }
}
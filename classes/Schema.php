<?php namespace Px;

class Schema extends \Laravel\Database\Schema {
  // Unlike default returns our overriden Schema instances for current driver.
  static function grammar(\Laravel\Database\Connection $connection) {
    if ($connection->driver() === 'mysql') {
      return new MySqlSchema($connection);
    }
  }
}
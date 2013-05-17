<?php
/*
  Plarx - Proger's Laravel Extension  | in public domain
  https://github.com/ProgerXP/Plarx   | http://laravel.ru
*/

require_once __DIR__.DS.'core.php';

Laravel\Autoloader::namespaces(array('Px' => __DIR__.DS.'classes'));
Laravel\Autoloader::alias('Px\\Plarx', 'Plarx');

$options = Laravel\Bundle::option('plarx', 'init') and Px\Plarx::init($options);

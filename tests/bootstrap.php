<?php
declare(strict_types=1);

use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;

$applicationRoot = dirname(__DIR__, 4);
$loader = require $applicationRoot . '/vendor/autoload.php';
$loader->addPsr4('Uskur\\Attachments\\Test\\', __DIR__ . '/');

// The host bootstrap guards its dedicated test database before rebuilding the schema.
require $applicationRoot . '/tests/bootstrap.php';

$connection = ConnectionManager::get('test');
if ($connection->config()['database'] !== 'test') {
    throw new RuntimeException('Tests require the dedicated test database named test.');
}
// A generic owner table keeps package tests independent of host domain models.
$schema = new TableSchema('attachment_test_owners');
$schema->addColumn('id', ['type' => 'uuid', 'null' => false]);
$schema->addColumn('name', ['type' => 'string', 'length' => 100, 'null' => false]);
$schema->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
foreach ($schema->createSql($connection) as $statement) {
    $connection->execute($statement);
}

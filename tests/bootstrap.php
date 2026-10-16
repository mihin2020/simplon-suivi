<?php

/*
 * The test database is a SQLite file outside version control: migrated once
 * per run by RefreshDatabase, then each test runs inside a rolled-back
 * transaction. A file (not :memory:) because the backup tests copy it.
 */
$database = __DIR__.'/../storage/framework/testing/testing.sqlite';

if (! is_dir(dirname($database))) {
    mkdir(dirname($database), 0777, true);
}

if (! file_exists($database)) {
    touch($database);
}

require __DIR__.'/../vendor/autoload.php';

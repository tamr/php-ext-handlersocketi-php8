<?php
/*
  Test table schema:
  CREATE DATABASE test;
  USE test;
  CREATE TABLE hs_test (
      id INT PRIMARY KEY,
      name VARCHAR(255),
      score INT
  ) ENGINE=InnoDB;
*/

if (!extension_loaded('handlersocketi')) {
    die("handlersocketi extension is not loaded\nAdd to php.ini: extension=handlersocketi.so\n");
}

echo "Extension loaded!\n";

// Check classes exist
$classes = ['HandlerSocketi', 'HandlerSocketi_Index', 'HandlerSocketi_Exception', 'HandlerSocketi_IO_Exception'];
foreach ($classes as $class) {
    echo "Class $class: " . (class_exists($class) ? 'OK' : 'MISSING') . "\n";
}

// Check supported methods
$expected_methods = ['__construct', 'auth', 'open_index', 'openIndex', 'has_open_index', 'hasOpenIndex'];
$rc = new ReflectionClass('HandlerSocketi');
foreach ($expected_methods as $method) {
    echo "Method HandlerSocketi::$method: " . ($rc->hasMethod($method) ? 'OK' : 'MISSING') . "\n";
}

// Check HandlerSocketi_Index methods
$expected_index_methods = ['find', 'insert', 'update', 'remove', 'multi', 'get_error', 'get_id'];
$rc = new ReflectionClass('HandlerSocketi_Index');
foreach ($expected_index_methods as $method) {
    echo "Method HandlerSocketi_Index::$method: " . ($rc->hasMethod($method) ? 'OK' : 'MISSING') . "\n";
}

// Check exception hierarchy
echo "HandlerSocketi_Exception extends Exception: " . (is_subclass_of('HandlerSocketi_Exception', 'Exception') ? 'OK' : 'FAIL') . "\n";

// Test connection error handling
try {
    $hs = new HandlerSocketi('127.0.0.1', 99999);
    echo "Connection to invalid port: should have thrown\n";
} catch (HandlerSocketi_Exception $e) {
    echo "Exception on bad connect: OK (" . $e->getMessage() . ")\n";
} catch (\Throwable $e) {
    echo "Exception on bad connect: " . get_class($e) . " - " . $e->getMessage() . "\n";
}

$hs_host = getenv('HS_HOST') ?: '127.0.0.1';
$hs_read_port = (int)(getenv('HS_READ_PORT') ?: 9998);
$hs_write_port = (int)(getenv('HS_WRITE_PORT') ?: 9999);
$hs_db = getenv('HS_DB') ?: 'test';
$hs_table = getenv('HS_TABLE') ?: 'hs_test';

echo "Connecting to $hs_host (read: $hs_read_port, write: $hs_write_port)\n";

try {
    // Write: open index on write port 
    $hs_w = new HandlerSocketi($hs_host, $hs_write_port, ['timeout' => 2]);
    $wi = $hs_w->openIndex($hs_db, $hs_table, ['id', 'name', 'score'], ['index' => 'PRIMARY']);
    echo "Write index opened: OK\n";

    // Clean up any previous test row
    $existing = $wi->find(['=' => 42]);
    if (!empty($existing)) {
        $wi->remove(['=' => 42]);
        echo "Cleaned up previous test row: OK\n";
    }

    // Insert 
    $wi->insert([42, 'hello', 100]);
    echo "Insert [42, 'hello', 100]: OK\n";

    // Read
    $hs_r = new HandlerSocketi($hs_host, $hs_read_port, ['timeout' => 2]);
    $ri = $hs_r->openIndex($hs_db, $hs_table, ['id', 'name', 'score'], ['index' => 'PRIMARY']);
    echo "Read index opened: OK\n";

    // Find 
    $rows = $ri->find(['=' => 42]);
    if (!empty($rows) && $rows[0][0] == 42 && $rows[0][1] === 'hello' && $rows[0][2] == 100) {
        echo "Find id=42: OK (got: [" . implode(', ', $rows[0]) . "])\n";
    } else {
        echo "Find id=42: FAIL (got: " . var_export($rows, true) . ")\n";
    }

    // Update 
    $wi->update(['=' => 42], ['U' => [42, 'world', 200]]);
    echo "Update id=42 -> [42, 'world', 200]: OK\n";

    $rows = $ri->find(['=' => 42]);
    if (!empty($rows) && $rows[0][1] === 'world' && $rows[0][2] == 200) {
        echo "Verify update: OK (got: [" . implode(', ', $rows[0]) . "])\n";
    } else {
        echo "Verify update: FAIL (got: " . var_export($rows, true) . ")\n";
    }

    // Remove 
    $wi->remove(['=' => 42]);
    echo "Remove id=42: OK\n";

    $rows = $ri->find(['=' => 42]);
    if (empty($rows)) {
        echo "Verify remove: OK (row gone)\n";
    } else {
        echo "Verify remove: FAIL (row still exists)\n";
    }

} catch (HandlerSocketi_Exception $e) {
    echo "HandlerSocketi error: " . $e->getMessage() . "\n";
} catch (\Throwable $e) {
    echo "Error: " . get_class($e) . " - " . $e->getMessage() . "\n";
}

echo "\nTest complete.\n";

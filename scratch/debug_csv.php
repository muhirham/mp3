<?php
$path = 'database/seeders/csv/products_master.csv';
$file = fopen($path, 'r');
$header = fgetcsv($file);
echo "Header count: " . count($header) . "\n";
print_r($header);

$row = fgetcsv($file);
echo "Row 1 count: " . count($row) . "\n";
print_r($row);
fclose($file);

<?php
$handle = fopen('php://output', 'w');
fputcsv($handle, ['1', 'Admin Pusat', 'admin']);
fclose($handle);

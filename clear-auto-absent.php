<?php
require_once 'config.php';

echo "Clearing previous auto-absent records from today...\n";
$result = $pdo->prepare("DELETE FROM attendance WHERE attendance_date = ? AND attendance_source = 'auto'");
$result->execute([date('Y-m-d')]);
echo "Deleted " . $result->rowCount() . " records.\n";
echo "Ready for new test.\n";
?>
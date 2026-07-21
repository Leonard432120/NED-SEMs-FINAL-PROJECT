<?php
require_once dirname(__DIR__) . '/config/db.php';
$c = get_db_connection();
$r = $c->query("DESCRIBE teacher_performance_history");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . ' | ' . $row['Key'] . "\n";
}
$r2 = $c->query("DESCRIBE teacher_performance_reviews");
echo "\n--- teacher_performance_reviews ---\n";
while ($row = $r2->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . "\n";
}
$c->close();

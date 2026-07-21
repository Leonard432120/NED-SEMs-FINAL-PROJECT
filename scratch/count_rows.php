<?php
require_once dirname(__DIR__) . '/config/db.php';
$conn = get_db_connection();
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    $t = $row[0];
    $cnt = $conn->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0];
    echo "$t: $cnt\n";
}
$conn->close();

<?php
$dbc = mysqli_connect("localhost", "username", "password", "databasename");
if (!$dbc) {
    die("Database connection failed: " . mysqli_error($dbc));
}
?>

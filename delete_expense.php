<?php
require __DIR__ . "/config/db_connection.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php?tab=view");
    exit;
}

$id = $_POST["expense_id"] ?? "";

if (!ctype_digit((string)$id)) {
    header("Location: index.php?tab=view");
    exit;
}

$stmt = mysqli_prepare(
    $con,
    "DELETE FROM expenses WHERE expense_id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header("Location: index.php?tab=view&deleted=1");
exit;
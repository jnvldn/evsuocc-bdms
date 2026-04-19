<?php
require_once __DIR__ . "/require_login.php";
require_once __DIR__ . "/db.php";

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "DELETE FROM donors WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo "Success";
    } else {
        echo "Error";
    }
}

$conn->close();
?>

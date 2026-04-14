<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bdms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $donor_id = $_POST["donor_id"];
  $blood_type = $_POST["blood_type"];
  $quantity = $_POST["quantity"];
  $collection_date = $_POST["collection_date"];
  $expiry_date = $_POST["expiry_date"];
  $status = $_POST["status"];
  $remarks = $_POST["remarks"];

  $sql = "INSERT INTO blood_inventory (donor_id, blood_type, quantity, collection_date, expiry_date, status, remarks)
          VALUES ('$donor_id', '$blood_type', '$quantity', '$collection_date', '$expiry_date', '$status', '$remarks')";

  if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>Blood inventory record added successfully!</p>";
    echo "<a href='dashboard.php'>Back to Dashboard</a>";
  } else {
    echo "<p style='color: red;'>Error: " . $sql . "<br>" . $conn->error . "</p>";
  }
}

$conn->close();
?>

<?php
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . "/db.php";

$sql = "SELECT blood_type, 
               SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END) AS available_quantity,
               SUM(CASE WHEN donation_status = 'Expired' THEN blood_quantity ELSE 0 END) AS expired_quantity
        FROM donors
        GROUP BY blood_type
        ORDER BY blood_type";
$result = $conn->query($sql);

$bloodTypes = [];
$availableQuantities = [];
$expiredQuantities = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bloodTypes[] = $row['blood_type'];
        $availableQuantities[] = (int)$row['available_quantity'];
        $expiredQuantities[] = (int)$row['expired_quantity'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Blood Inventory Graph</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Montserrat', sans-serif;
      background: #f9f9f9;
      color: #4d4d4d;
      margin: 0;
      padding: 50px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    h2 {
      color: #b30000;
      font-size: 36px;
      font-weight: 500;
      margin-bottom: 20px;
      position: relative;
      display: flex;
      align-items: center;
    }
    h2 i {
      margin-right: 10px;
      font-size: 36px;
      color: #b30000;
    }
    canvas {
      max-width: 90%;
      max-height: 400px;
      margin: 20px 0;
    }
    .flip-icon {
      font-size: 36px;
      color: #b30000;
      cursor: pointer;
      transition: transform 0.3s;
      margin-top: 30px;
    }
    .flip-icon:hover {
      transform: rotate(180deg);
    }
  </style>
</head>
<body>

<h2><i class="fas fa-chart-bar"></i>Blood Inventory Graph</h2>

<canvas id="bloodChart"></canvas>

<a href="blood_inventory.php" class="flip-icon"><i class="fas fa-sync-alt"></i></a>

<script>
  const ctx = document.getElementById('bloodChart').getContext('2d');
  const bloodChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?php echo json_encode($bloodTypes); ?>,
      datasets: [{
        label: 'Available Blood (mL)',
        data: <?php echo json_encode($availableQuantities); ?>,
        backgroundColor: 'rgba(255, 99, 132, 0.6)',
        borderColor: 'rgba(255, 99, 132, 1)',
        borderWidth: 1
      }, {
        label: 'Expired Blood (mL)',
        data: <?php echo json_encode($expiredQuantities); ?>,
        backgroundColor: 'rgba(231, 76, 60, 0.6)',
        borderColor: 'rgba(231, 76, 60, 1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      scales: {
        x: {
          beginAtZero: true
        },
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Blood Quantity (mL)'
          }
        }
      }
    }
  });
</script>

</body>
</html>

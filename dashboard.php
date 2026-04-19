<?php  
require_once __DIR__ . "/require_login.php";
require_once __DIR__ . "/db.php";

$conn->query("UPDATE donors 
              SET donation_status = 'Expired' 
              WHERE collection_date < CURDATE() - INTERVAL 42 DAY");

$conn->query("UPDATE donors 
              SET donation_status = 'Active' 
              WHERE collection_date >= CURDATE() - INTERVAL 42 DAY");

$sql_donors = "SELECT COUNT(*) as total_donors FROM donors";
$result_donors = $conn->query($sql_donors);
$total_donors = ($result_donors && $result_donors->num_rows > 0) ? $result_donors->fetch_assoc()['total_donors'] : 0;

$sql_blood = "SELECT COALESCE(SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END), 0) AS available_quantity FROM donors";
$result_blood = $conn->query($sql_blood);
$available_blood_units = ($result_blood && $result_blood->num_rows > 0) ? $result_blood->fetch_assoc()['available_quantity'] : 0;

$sql_blood_by_type = "
  SELECT blood_type, 
         COALESCE(SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END), 0) AS available_quantity 
  FROM donors 
  GROUP BY blood_type 
  ORDER BY blood_type
";
$result_blood_by_type = $conn->query($sql_blood_by_type);

$sql_classification = "SELECT classification, COUNT(*) as total FROM donors GROUP BY classification";
$result_classification = $conn->query($sql_classification);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EVSU-OCC Blood Donation Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      background-color: #f4f4f4;
      display: flex;
    }
    .sidebar {
      background-color: #b30000;
      color: white;
      width: 250px;
      height: 100vh;
      padding: 20px;
      position: fixed;
      left: 0;
      top: 0;
      box-sizing: border-box;
      transform: translateX(-250px);
      transition: transform 0.3s ease;
      z-index: 999;
    }
    .sidebar.active {
      transform: translateX(0);
    }
    .sidebar h2 {
      margin-top: 0;
      font-size: 20px;
      font-weight: 500;
    }
    .sidebar ul {
      list-style: none;
      padding: 0;
      margin-top: 60px;
      max-height: 90%;
      overflow-y: auto;
      scrollbar-width: thin;
      scrollbar-color: #ffcccc #b30000;
    }
    .sidebar ul::-webkit-scrollbar {
      width: 6px;
    }
    .sidebar ul::-webkit-scrollbar-track {
      background: #b30000;
    }
    .sidebar ul::-webkit-scrollbar-thumb {
      background-color: #ffcccc;
      border-radius: 10px;
    }
    .sidebar ul li {
      margin: 15px 0;
    }
    .sidebar ul li a {
      color: white;
      text-decoration: none;
      font-size: 16px;
      display: flex;
      align-items: center;
      padding: 10px 15px;
      border-radius: 5px;
      transition: background-color 0.3s ease, padding-left 0.3s ease;
      text-align: left;
      justify-content: flex-start;
      font-weight: 400;
    }
    .sidebar ul li a i {
      margin-right: 10px;
    }
    .sidebar ul li a:hover {
      background-color:rgb(140, 31, 31);
      padding-left: 25px;
      color: white;
    }
    header {
      background-color: #b30000;
      color: white;
      padding: 15px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 1000;
    }
    header h1 {
      font-size: 20px;
      margin: 0 0 0 18px;
      flex-grow: 1;
      font-weight: 500;
    }
    header img {
      height: 40px;
      margin-left: 15px;
    }
    .nav-toggle {
      font-size: 24px;
      background: none;
      border: none;
      color: white;
      cursor: pointer;
    }
    .logout-btn {
      color: white;
      font-size: 18px;
      padding: 5px 10px;
      background-color: #b30000;
      border-radius: 5px;
      border: 2px solid red;
      transition: background-color 0.3s ease;
      text-decoration: none;
      margin-left: 10px;
      margin-right: 40px;
      font-weight: 500;
    }
    .logout-btn:hover {
      background-color: #b30000;
      border-color: rgb(190, 169, 169);
    }
    .main-content {
      margin-left: 0;
      padding: 140px 30px 30px 30px;
      width: 100%;
      transition: margin-left 0.3s ease;
    }
    .main-content.shifted {
      margin-left: 250px;
    }
    .card {
      background-color: #fdf1f1;
      padding: 20px;
      margin-bottom: 20px;
      border-left: 7px solid #b30000;
      border-radius: 5px;
      width: fit-content;
    }
    .metrics {
      display: flex;
      gap: 50px;
      justify-content: flex-start;
      margin-top: 20px;
      margin-left: 50px;
      max-width: 4200px;
    }
    .metric-box {
      flex: 1 1 23%;
      background-color: #ffeaea;
      border: 3px solid #b30000;
      padding: 30px;
      border-radius: 10px;
      text-align: center;
      transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
      cursor: pointer;
      font-weight: 400;
    }
    .metric-box h4 {
      margin-bottom: 10px;
      color: #8b0000;
      font-size: 20px;
    }
    .metric-box p {
      font-size: 18px;
      color: #333;
      margin: 0;
      text-align: center;
    }
    .metric-box ul {
      text-align: left;
      padding-left: 70px;
      list-style: disc;
    }
    .metric-box:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
      background-color: #ffe0e0;
    }
    .quote {
      text-align: center;
      font-size: 2.5em;
      font-weight: bold;
      color: #b30000;
      margin-top: 40px;
      font-family: 'Poppins', sans-serif;
      font-weight: 500;
    }
    .quote::before,
    .quote::after {
      content: '"';
      font-size: 1.5em;
      font-weight: bold;
    }
    @media (max-width: 768px) {
      .sidebar {
        width: 200px;
        transform: translateX(-210px);
      }
      .sidebar.active {
        transform: translateX(0);
      }
      .main-content.shifted {
        margin-left: 200px;
      }
      .metric-box {
        flex: 1 1 100%;
        max-width: 100%;
        padding: 20px;
      }
      .metrics {
        flex-direction: column;
        align-items: center;
      }
    }
    .swal2-confirm:hover {
      background-color: #8b0000 !important;
      color: white !important;
    }
    .swal2-cancel:hover {
      background-color: #d33 !important;
      color: white !important;
    }
  </style>
</head>
<body>

  <div class="sidebar" id="sidebar">
    <h2>Menu</h2>
    <ul>
      <li><a href="add.php"><i class="fas fa-user-plus"></i> Add Donor</a></li>
      <li><a href="donors_list.php"><i class="fas fa-users"></i> Donors Lists</a></li>
      <li><a href="donor_reports.php"><i class="fas fa-file-alt"></i> Donor Reports</a></li>
      <li><a href="blood_inventory.php"><i class="fas fa-tint"></i> Blood Inventory</a></li>
      <li><a href="record_donation.php"><i class="fas fa-hand-holding-medical"></i> Record Donation</a></li>
      <li><a href="audit_log.php"><i class="fas fa-clipboard-list"></i> Audit Log</a></li>
    </ul>
  </div>

  <header>
    <button class="nav-toggle" onclick="toggleSidebar()">☰</button>
    <img src="evsulogo.png" alt="EVSU Logo">
    <h1>EVSU-OCC Blood Donation Dashboard</h1>
    <a href="javascript:void(0);" class="logout-btn" onclick="confirmLogout(event)">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </header>

  <main class="main-content" id="mainContent">
    <div class="card">
      <h3>Overview</h3>

      <div class="quote">Donate blood, save lives, be a hero!</div>

      <div class="metrics">
        <div class="metric-box">
          <h4><i class="fas fa-user-friends"></i> Total Donors</h4>
          <p><?php echo $total_donors; ?></p>
        </div>

        <div class="metric-box">
          <h4><i class="fas fa-tint"></i> Available Blood Units</h4>
          <p><?php echo $available_blood_units; ?></p>
        </div>

        <div class="metric-box">
          <h4><i class="fas fa-vials"></i> Available Blood Units by Type</h4>
          <ul>
            <?php
              if ($result_blood_by_type && $result_blood_by_type->num_rows > 0) {
                mysqli_data_seek($result_blood_by_type, 0);
                while ($row = $result_blood_by_type->fetch_assoc()) {
                  echo "<li>" . htmlspecialchars($row['blood_type']) . ": " . $row['available_quantity'] . " units</li>";
                }
              } else {
                echo "<li>No data available</li>";
              }
            ?>
          </ul>
        </div>

        <div class="metric-box">
          <h4><i class="fas fa-user-tag"></i> Classified Donors</h4>
          <ul>
            <?php
              if ($result_classification && $result_classification->num_rows > 0) {
                while ($row = $result_classification->fetch_assoc()) {
                  echo "<li>" . htmlspecialchars($row['classification']) . ": " . $row['total'] . "</li>";
                }
              } else {
                echo "<li>No data</li>";
              }
            ?>
          </ul>
        </div>
      </div>
    </div>
  </main>

  <script>
    function toggleSidebar() {
      const sidebar = document.getElementById("sidebar");
      const mainContent = document.getElementById("mainContent");
      sidebar.classList.toggle("active");
      mainContent.classList.toggle("shifted");
    }

    function confirmLogout(event) {
      event.preventDefault();
      Swal.fire({
        title: 'Are you sure you want to logout?',
        text: "You will be logged out of your session.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Logout',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        confirmButtonColor: '#8b0000',
        cancelButtonColor: '#d33'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'logout.php';
        }
      });
    }
  </script>

</body>
</html>

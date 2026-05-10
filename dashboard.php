<?php
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_profile_bar.php';
require_once __DIR__ . '/bdms_low_inventory_alerts.php';
require_once __DIR__ . '/db.php';

$is_administrator = bdms_is_administrator();

$conn->query("UPDATE donors 
              SET donation_status = 'Expired' 
              WHERE collection_date < CURDATE() - INTERVAL 42 DAY");

$conn->query("UPDATE donors 
              SET donation_status = 'Active' 
              WHERE collection_date >= CURDATE() - INTERVAL 42 DAY");

$sql_donors = 'SELECT COUNT(*) as total_donors FROM donors';
$result_donors = $conn->query($sql_donors);
$total_donors = ($result_donors && $result_donors->num_rows > 0) ? (int) $result_donors->fetch_assoc()['total_donors'] : 0;

$sql_blood = "SELECT COALESCE(SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END), 0) AS available_quantity FROM donors";
$result_blood = $conn->query($sql_blood);
$available_blood_units = ($result_blood && $result_blood->num_rows > 0) ? (int) $result_blood->fetch_assoc()['available_quantity'] : 0;

$sql_blood_by_type = "
  SELECT blood_type, 
         COALESCE(SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END), 0) AS available_quantity 
  FROM donors 
  GROUP BY blood_type 
  ORDER BY blood_type
";
$result_blood_by_type = $conn->query($sql_blood_by_type);

$sql_classification = 'SELECT classification, COUNT(*) as total FROM donors GROUP BY classification';
$result_classification = $conn->query($sql_classification);

$low_stock_alerts = bdms_fetch_low_stock_alerts($conn);

// US-04: one modal per login session (cleared on logout); staff and admin both see it once.
$show_low_stock_login_modal = $low_stock_alerts !== [] && empty($_SESSION['bdms_us04_low_inventory_modal_shown']);
if ($show_low_stock_login_modal) {
  $_SESSION['bdms_us04_low_inventory_modal_shown'] = true;
}

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
  <?php bdms_profile_bar_print_styles(); ?>
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
      flex: 1 1 auto;
      min-width: 0;
      font-weight: 500;
    }
    .header-right {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-shrink: 0;
      margin-left: 12px;
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
    .alerts-card {
      background-color: #fff;
      border-left: 7px solid #ff9800;
      border-radius: 8px;
      padding: 16px 20px;
      margin-bottom: 18px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.06);
      max-width: 1200px;
    }
    .alerts-card h3 {
      margin: 0 0 8px 0;
      color: #b30000;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .alerts-card .subtitle {
      margin: 0 0 12px 0;
      color: #555;
      font-size: 14px;
    }
    .alerts-list {
      margin: 0;
      padding-left: 18px;
    }
    .alerts-list li {
      margin: 6px 0;
      color: #8a4b00;
      font-weight: 500;
    }
    .alerts-ok {
      color: #1b7f3a;
      font-weight: 600;
      margin: 0;
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
    a.metric-box {
      text-decoration: none;
      color: inherit;
      box-sizing: border-box;
    }
    a.metric-box:focus-visible {
      outline: 3px solid #8b0000;
      outline-offset: 2px;
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
    .staff-delete-hint {
      font-size: 13px;
      line-height: 1.4;
      padding: 10px 14px;
      margin-top: 16px;
      background: rgba(0,0,0,0.12);
      border-radius: 8px;
      border: 1px solid rgba(255,255,255,0.25);
    }
  </style>
</head>
<body>

  <div class="sidebar" id="sidebar">
    <h2>Menu</h2>
    <ul>
      <li><a href="add.php"><i class="fas fa-user-plus"></i> Add Donor</a></li>
      <li><a href="donors_list.php"><i class="fas fa-users"></i> Donors Lists</a></li>
      <li><a href="reports.php"><i class="fas fa-file-alt"></i> Generate Report</a></li>
      <li><a href="blood_inventory.php"><i class="fas fa-tint"></i> Blood Inventory</a></li>
      <li><a href="record_donation.php"><i class="fas fa-hand-holding-medical"></i> Record Donation</a></li>
      <?php if ($is_administrator): ?>
      <li><a href="user_management.php"><i class="fas fa-users-cog"></i> User Management</a></li>
      <?php else: ?>
      <li class="staff-delete-hint"><i class="fas fa-info-circle"></i> <strong>Staff:</strong> you can add, edit, and view donors and run reports, but <strong>only an administrator can delete</strong> a donor record.</li>
      <?php endif; ?>
    </ul>
  </div>

  <header>
    <button class="nav-toggle" onclick="toggleSidebar()">☰</button>
    <img src="evsulogo.png" alt="EVSU Logo">
    <h1>EVSU-OCC Blood Donation Dashboard</h1>
    <div class="header-right">
      <?php bdms_profile_bar_render(); ?>
      <a href="javascript:void(0);" class="logout-btn" onclick="confirmLogout(event)">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </div>
  </header>

  <main class="main-content" id="mainContent">
    <div class="alerts-card">
      <h3><i class="fas fa-triangle-exclamation"></i> Low Inventory Alerts</h3>
      <?php if (!empty($low_stock_alerts)) : ?>
        <p class="subtitle">The following blood types are below the defined threshold. Please plan donation drives immediately.</p>
        <ul class="alerts-list">
          <?php foreach ($low_stock_alerts as $a) : ?>
            <li>
              <?php echo htmlspecialchars($a['blood_type']); ?>
              — <?php echo (int)$a['available_quantity']; ?> mL available (threshold: <?php echo (int)$a['threshold_ml']; ?> mL)
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else : ?>
        <p class="alerts-ok"><i class="fas fa-circle-check"></i> All blood types are above the defined threshold.</p>
      <?php endif; ?>
    </div>
    <div class="card">
      <h3>Overview</h3>

      <div class="quote">Donate blood, save lives, be a hero!</div>

      <div class="metrics">
        <a href="donors_list.php" class="metric-box" aria-label="Open donors list">
          <h4><i class="fas fa-user-friends"></i> Total Donors</h4>
          <p><?php echo (int) $total_donors; ?></p>
        </a>

        <a href="blood_inventory.php" class="metric-box" aria-label="Open blood inventory">
          <h4><i class="fas fa-tint"></i> Available Blood Units</h4>
          <p><?php echo (int) $available_blood_units; ?></p>
        </a>

        <a href="graph_page.php" class="metric-box" aria-label="Open blood inventory chart by type">
          <h4><i class="fas fa-vials"></i> Available Blood Units by Type</h4>
          <ul>
            <?php
              if ($result_blood_by_type && $result_blood_by_type->num_rows > 0) {
                mysqli_data_seek($result_blood_by_type, 0);
                while ($row = $result_blood_by_type->fetch_assoc()) {
                  echo '<li>' . htmlspecialchars((string) $row['blood_type']) . ': ' . htmlspecialchars((string) $row['available_quantity']) . ' units</li>';
                }
              } else {
                echo '<li>No data available</li>';
              }
            ?>
          </ul>
        </a>

        <a href="reports.php" class="metric-box" aria-label="Open donor reports">
          <h4><i class="fas fa-user-tag"></i> Classified Donors</h4>
          <ul>
            <?php
              if ($result_classification && $result_classification->num_rows > 0) {
                while ($row = $result_classification->fetch_assoc()) {
                  echo '<li>' . htmlspecialchars((string) $row['classification']) . ': ' . htmlspecialchars((string) $row['total']) . '</li>';
                }
              } else {
                echo '<li>No data</li>';
              }
            ?>
          </ul>
        </a>
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

    (function () {
      const p = new URLSearchParams(window.location.search);
      if (p.get('access') === 'denied' || p.get('reports') === 'denied') {
        Swal.fire({
          icon: 'error',
          title: 'Access denied',
          text: 'That page is for administrators only (for example User Management).',
          confirmButtonColor: '#8b0000'
        });
        if (window.history.replaceState) {
          window.history.replaceState({}, '', 'dashboard.php');
        }
      }
    })();

    <?php if ($show_low_stock_login_modal) : ?>
    (function () {
      const rows = <?php echo json_encode($low_stock_alerts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
      if (!rows || !rows.length || typeof Swal === 'undefined') return;
      const listHtml = '<ul style="text-align:left;margin:0.5em 0;padding-left:1.2em;">' +
        rows.map(function (r) {
          return '<li><strong>' + String(r.blood_type) + '</strong> — ' + Number(r.available_quantity) +
            ' mL available (threshold ' + Number(r.threshold_ml) + ' mL)</li>';
        }).join('') + '</ul>';
      Swal.fire({
        title: 'Low blood inventory',
        html: '<p style="margin:0 0 8px 0;font-weight:500;">The following types are below the defined threshold. Please plan donation drives.</p>' + listHtml,
        icon: 'warning',
        confirmButtonText: 'OK',
        confirmButtonColor: '#b30000',
        allowOutsideClick: true
      });
    })();
    <?php endif; ?>

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

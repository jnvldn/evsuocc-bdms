<?php
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_low_inventory_alerts.php';
require_once __DIR__ . '/db.php';

$conn->query("UPDATE donors 
              SET donation_status = 'Expired' 
              WHERE collection_date < CURDATE() - INTERVAL 42 DAY");

$conn->query("UPDATE donors 
              SET donation_status = 'Active' 
              WHERE collection_date >= CURDATE() - INTERVAL 42 DAY");

$low_stock_alerts = bdms_fetch_low_stock_alerts($conn);
$low_stock_types = [];
foreach ($low_stock_alerts as $la) {
    $low_stock_types[(string) $la['blood_type']] = true;
}

$show_low_stock_login_modal = $low_stock_alerts !== [] && empty($_SESSION['bdms_us04_low_inventory_modal_shown']);
if ($show_low_stock_login_modal) {
    $_SESSION['bdms_us04_low_inventory_modal_shown'] = true;
}

$sql = "SELECT blood_type, 
               SUM(CASE WHEN donation_status = 'Active' THEN blood_quantity ELSE 0 END) AS available_quantity,
               SUM(CASE WHEN donation_status = 'Expired' THEN blood_quantity ELSE 0 END) AS expired_quantity,
               MAX(collection_date + INTERVAL 42 DAY) AS expiration_date,
               MAX(last_updated) AS last_updated,
               donation_status
        FROM donors
        GROUP BY blood_type, donation_status
        ORDER BY blood_type";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Blood Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      font-family: 'Montserrat', sans-serif;
      background: #f5f5f5;
      color: #4d4d4d;
      padding: 40px;
      margin: 0;
    }
    h2 {
      color: #b30000;
      font-size: 32px;
      font-weight: 500;
      margin-bottom: 40px;
      position: relative;
    }
    .flip-icon {
      font-size: 24px;
      color: red;
      position: absolute;
      top: 0;
      right: 3px;
      cursor: pointer;
      transition: transform 0.3s;
    }
    .flip-icon:hover {
      transform: rotate(180deg);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    thead th {
      background-color: #f4f4f4;
      color: #b30000;
      padding: 12px;
      font-size: 14px;
      text-align: center;
      border: 1px solid #e0e0e0;
    }
    tbody td {
      padding: 12px;
      text-align: center;
      font-size: 14px;
      border: 1px solid #e0e0e0;
    }
    tbody tr:nth-child(even) {
      background-color: #f9f9f9;
    }
    .status-Active {
      color: green;
      font-weight: bold;
    }
    .status-Expired {
      color: red;
      font-weight: bold;
    }
    .status-Reserved {
      color: orange;
      font-weight: bold;
    }
    .button-wrapper {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      padding: 15px 0;
      background-color: #fff;
      display: flex;
      justify-content: center;
      gap: 20px;
      box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
      z-index: 1000;
    }
    .button-wrapper a {
      text-decoration: none;
    }
    .button-wrapper button {
      width: 200px;
      padding: 12px 25px;
      background: linear-gradient(135deg, #b30000, #800000);
      color: #fff;
      border: none;
      border-radius: 30px;
      font-size: 14px;
      cursor: pointer;
      font-weight: 500;
      box-shadow: 0 4px 10px rgba(179, 0, 0, 0.3);
      transition: all 0.4s ease;
      position: relative;
      overflow: hidden;
      z-index: 1;
    }
    .button-wrapper button::before {
      content: "";
      position: absolute;
      top: 0;
      left: -75%;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.15);
      transform: skewX(-45deg);
      transition: left 0.5s ease;
      z-index: 0;
    }
    .button-wrapper button:hover::before {
      left: 125%;
    }
    .button-wrapper button:hover {
      transform: scale(1.05);
      box-shadow: 0 6px 20px rgba(179, 0, 0, 0.6);
    }
    .button-wrapper button i {
      margin-right: 8px;
    }
    @media (max-width: 768px) {
      body {
        padding: 20px;
      }
      table {
        font-size: 12px;
      }
      .button-wrapper {
        flex-direction: column;
        gap: 10px;
      }
    }
    .modal {
      display: none;
      position: fixed;
      z-index: 99;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      backdrop-filter: blur(10px);
      background-color: rgba(0, 0, 0, 0.3);
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background-color: #fff5f5;
      padding: 25px;
      border-radius: 10px;
      width: 80%;
      max-width: 700px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
      animation: fadeIn 0.3s ease;
    }
    .modal-content h3 {
      margin-top: 0;
      color: #b30000;
      text-align: center;
    }
    .close {
      float: right;
      font-size: 22px;
      font-weight: bold;
      color: #b30000;
      cursor: pointer;
      transition: transform 0.3s ease, color 0.3s ease;
    }
    .close:hover {
      transform: scale(1.2) rotate(90deg);
      color: #800000;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    .modal table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    .modal th, .modal td {
      border: 1px solid #e6b8b8;
      padding: 10px;
      text-align: center;
    }
    .modal th {
      background-color: #cc0000;
      color: white;
    }
    .button-wrapper.blur {
      filter: blur(5px);
      pointer-events: none;
    }
    tr.low-stock-row {
      outline: 2px solid #ff9800;
      background-color: #fff8e1 !important;
    }
    tr.low-stock-row td:first-child {
      font-weight: 700;
      color: #b30000;
    }
  </style>
</head>
<body>

<div class="button-wrapper">
  <a href="dashboard.php">
    <button>
      <i class="fas fa-tachometer-alt"></i> Dashboard
    </button>
  </a>
  <a href="update_threshold.php">
    <button>
      <i class="fas fa-sliders-h"></i> Update Threshold
    </button>
  </a>
  <a href="#" id="archiveBtnLink">
    <button id="archiveBtn">
      <i class="fas fa-calendar-xmark"></i> Expired Blood
    </button>
  </a>
</div>

<h2><i class="fas fa-cogs"></i> Blood Inventory
  <a href="graph_page.php" class="flip-icon"><i class="fas fa-sync-alt"></i></a>
</h2>

<?php if ($low_stock_alerts !== []) : ?>
<p style="max-width:900px;margin:0 auto 20px auto;padding:14px 18px;background:#fff3e0;border-left:5px solid #ff9800;border-radius:8px;color:#5d4037;font-size:14px;line-height:1.5;">
  <strong><i class="fas fa-triangle-exclamation"></i> Low inventory (US-04):</strong>
  <?php foreach ($low_stock_alerts as $idx => $la) : ?>
    <?php if ($idx > 0) {
        echo '; ';
    } ?>
    <strong><?php echo htmlspecialchars((string) $la['blood_type'], ENT_QUOTES, 'UTF-8'); ?></strong>
    at <?php echo (int) $la['available_quantity']; ?> mL (threshold <?php echo (int) $la['threshold_ml']; ?> mL)
  <?php endforeach; ?>
  — plan donation drives as needed.
</p>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th>Blood Type</th>
      <th>Available Units (mL)</th>
      <th>Expired Units (mL)</th>
      <th>Expiration Date</th>
      <th>Last Updated</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $bt = (string) $row['blood_type'];
            $isLow = isset($low_stock_types[$bt]) && ($row['donation_status'] === 'Active');
            $rowClass = $isLow ? " class='low-stock-row'" : '';
            echo '<tr' . $rowClass . '>';
            echo "<td>" . htmlspecialchars($bt) . "</td>";
            echo "<td>" . (int)$row['available_quantity'] . " mL</td>";
            echo "<td>" . (int)$row['expired_quantity'] . " mL</td>";
            echo "<td>" . ($row['expiration_date'] ? date("Y-m-d", strtotime($row['expiration_date'])) : 'N/A') . "</td>";
            echo "<td>" . ($row['last_updated'] ? date("Y-m-d H:i", strtotime($row['last_updated'])) : 'N/A') . "</td>";
            echo "<td class='status-" . htmlspecialchars($row['donation_status']) . "'>" . $row['donation_status'] . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='6'>No blood inventory data available</td></tr>";
    }
    ?>
  </tbody>
</table>

<div id="archiveModal" class="modal">
  <div class="modal-content">
    <span class="close" id="closeModal">&times;</span>
    <h3>Expired Blood Donations</h3>
    <table>
      <thead>
        <tr>
          <th>Blood Type</th>
          <th>Quantity (mL)</th>
          <th>Expiration Date</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $archived = $conn->query("SELECT blood_type, blood_quantity, (collection_date + INTERVAL 42 DAY) AS expiration_date FROM donors WHERE donation_status = 'Expired' ORDER BY expiration_date DESC");
        if ($archived && $archived->num_rows > 0) {
            while($row = $archived->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['blood_type']) . "</td>";
                echo "<td>" . (int)$row['blood_quantity'] . " mL</td>";
                echo "<td>" . date("Y-m-d", strtotime($row['expiration_date'])) . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='3'>No archived donations found.</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

<script>
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

  const btn = document.getElementById('archiveBtn');
  const modal = document.getElementById('archiveModal');
  const close = document.getElementById('closeModal');
  const buttonWrapper = document.querySelector('.button-wrapper');

  btn.onclick = () => {
    modal.style.display = 'flex';
    buttonWrapper.classList.add('blur');
  };

  close.onclick = () => {
    modal.style.display = 'none';
    buttonWrapper.classList.remove('blur');
  };

  window.onclick = (e) => {
    if (e.target == modal) {
      modal.style.display = 'none';
      buttonWrapper.classList.remove('blur');
    }
  };
</script>

</body>
</html>

<?php
$conn->close();
?>

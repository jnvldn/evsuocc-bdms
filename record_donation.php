<?php
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/donor_helpers.php';

// Make MySQLi throw exceptions on errors so we can surface the real cause.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$showAlert = '';
$validationErrors = [];
$lastDbError = '';

// Pre-flight schema checks so we can show a precise fix message.
$schemaIssues = [];

$hasColumns = static function (mysqli $conn, string $table, array $columns): array {
    $missing = [];
    $in = "'" . implode("','", array_map(static fn ($c) => $conn->real_escape_string((string) $c), $columns)) . "'";
    $sql = "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '" . $conn->real_escape_string($table) . "'
          AND COLUMN_NAME IN ($in)
    ";
    $res = $conn->query($sql);
    $found = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $found[(string) $row['COLUMN_NAME']] = true;
        }
    }
    foreach ($columns as $c) {
        if (!isset($found[$c])) {
            $missing[] = $c;
        }
    }
    return $missing;
};

$donationsCheck = $conn->query("SHOW TABLES LIKE 'donations'");
if (!($donationsCheck && $donationsCheck->num_rows > 0)) {
    $schemaIssues[] = 'Missing table: donations (run schema_us02.sql)';
}

$auditCheck = $conn->query("SHOW TABLES LIKE 'audit_log'");
if (!($auditCheck && $auditCheck->num_rows > 0)) {
    $schemaIssues[] = 'Missing table: audit_log (run schema_us02.sql)';
}

// Columns required by this page.
$missingDonorsCols = $hasColumns($conn, 'donors', [
    'id',
    'blood_type',
    'blood_quantity',
    'collection_date',
    'donation_date',
    'donation_status',
    'donation_history',
    'donation_dates',
    'number_of_donations',
    'medical_eligibility',
]);
if ($missingDonorsCols) {
    $schemaIssues[] = 'Missing columns in donors: ' . implode(', ', $missingDonorsCols) . ' (import/apply latest bdms.sql)';
}

$missingInvCols = $hasColumns($conn, 'blood_inventory', [
    'id',
    'blood_type',
    'quantity',
    'status',
    'donated_by',
]);
if ($missingInvCols) {
    $schemaIssues[] = 'Missing columns in blood_inventory: ' . implode(', ', $missingInvCols) . ' (import/apply latest bdms.sql)';
}

// `blood_inventory.id` must be AUTO_INCREMENT because inserts omit `id`.
$invIdOk = false;
$invIdRes = $conn->query("
    SELECT EXTRA
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'blood_inventory'
      AND COLUMN_NAME = 'id'
    LIMIT 1
");
if ($invIdRes && ($r = $invIdRes->fetch_assoc())) {
    $invIdOk = stripos((string)$r['EXTRA'], 'auto_increment') !== false;
}
if (!$invIdOk) {
    $schemaIssues[] = 'Column blood_inventory.id is not AUTO_INCREMENT (import/apply bdms.sql ALTER TABLE for blood_inventory)';
}

$schemaOk = empty($schemaIssues);

$donorsList = $conn->query(
    'SELECT id, name, blood_type, contact_number, email FROM donors ORDER BY name ASC'
);
$donorsRows = [];
if ($donorsList) {
    while ($row = $donorsList->fetch_assoc()) {
        $donorsRows[] = $row;
    }
}

$preselectId = isset($_GET['donor_id']) ? (int) $_GET['donor_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$schemaOk) {
        $showAlert = 'schema';
    } else {
        $check = donor_validate_donation_inputs($_POST);
        if (!$check['ok']) {
            $validationErrors = $check['errors'];
            $showAlert = 'validation';
        } else {
            $donorId = (int) $_POST['donor_id'];
            $bloodType = (string) $_POST['blood_type'];
            $quantityMl = (int) $_POST['quantity_ml'];
            $donationDate = trim((string) $_POST['donation_date']);
            $eligibility = (string) $_POST['eligibility_status'];
            $performedBy = isset($_SESSION['user']) ? (string) $_SESSION['user'] : 'staff';

            $sel = $conn->prepare('SELECT id, donation_history, donation_dates, number_of_donations FROM donors WHERE id = ? FOR UPDATE');
            if ($sel === false) {
                $lastDbError = $conn->error;
                $showAlert = 'error';
            } else {
                $sel->bind_param('i', $donorId);
                $conn->begin_transaction();
                try {
                    $sel->execute();
                    $res = $sel->get_result();
                    $donorRow = $res->fetch_assoc();
                    $sel->close();
                    if (!$donorRow) {
                        throw new RuntimeException('Donor not found.');
                    }

                    $prevHist = (string) ($donorRow['donation_history'] ?? '');
                    $prevDates = $donorRow['donation_dates'];
                    $prevCount = (int) ($donorRow['number_of_donations'] ?? 0);

                    $newDates = ($prevDates === null || trim((string) $prevDates) === '')
                        ? $donationDate
                        : trim((string) $prevDates) . ', ' . $donationDate;

                    $newCount = $prevCount + 1;
                    $eligibleForStock = $eligibility === 'Eligible';

                    $newHist = $prevHist;
                    if ($eligibleForStock && $prevHist === 'First Time') {
                        $newHist = 'Regular Donor';
                    }

                    $insDon = $conn->prepare(
                        'INSERT INTO donations (donor_id, donation_date, blood_type, quantity_ml, eligibility_status)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    if ($insDon === false) {
                        throw new RuntimeException('Prepare failed.');
                    }
                    $insDon->bind_param(
                        'isiss',
                        $donorId,
                        $donationDate,
                        $bloodType,
                        $quantityMl,
                        $eligibility
                    );
                    $insDon->execute();
                    $donationPk = (int) $conn->insert_id;
                    $insDon->close();

                    if ($eligibleForStock) {
                        $upd = $conn->prepare(
                            'UPDATE donors SET
                                blood_type = ?,
                                blood_quantity = blood_quantity + ?,
                                quantity_ml = ?,
                                blood_quantity_ml = ?,
                                collection_date = ?,
                                donation_date = ?,
                                donation_status = \'Active\',
                                number_of_donations = ?,
                                donation_dates = ?,
                                donation_history = ?,
                                medical_eligibility = ?
                             WHERE id = ?'
                        );
                        if ($upd === false) {
                            throw new RuntimeException('Prepare failed.');
                        }
                        $upd->bind_param(
                            'siiississsi',
                            $bloodType,
                            $quantityMl,
                            $quantityMl,
                            $quantityMl,
                            $donationDate,
                            $donationDate,
                            $newCount,
                            $newDates,
                            $newHist,
                            $eligibility,
                            $donorId
                        );
                        $upd->execute();
                        $upd->close();

                        $inv = $conn->prepare(
                            'INSERT INTO blood_inventory (blood_type, quantity, status, donated_by)
                             VALUES (?, ?, \'Available\', ?)'
                        );
                        if ($inv === false) {
                            throw new RuntimeException('Prepare failed.');
                        }
                        $inv->bind_param('sii', $bloodType, $quantityMl, $donorId);
                        $inv->execute();
                        $inv->close();
                    } else {
                        $upd = $conn->prepare(
                            'UPDATE donors SET
                                blood_type = ?,
                                donation_date = ?,
                                number_of_donations = ?,
                                donation_dates = ?,
                                donation_history = ?,
                                medical_eligibility = ?
                             WHERE id = ?'
                        );
                        if ($upd === false) {
                            throw new RuntimeException('Prepare failed.');
                        }
                        $upd->bind_param(
                            'ssisssi',
                            $bloodType,
                            $donationDate,
                            $newCount,
                            $newDates,
                            $newHist,
                            $eligibility,
                            $donorId
                        );
                        $upd->execute();
                        $upd->close();
                    }

                    $details = json_encode(
                        [
                            'donation_id' => $donationPk,
                            'donor_id' => $donorId,
                            'quantity_ml' => $quantityMl,
                            'blood_type' => $bloodType,
                            'donation_date' => $donationDate,
                            'inventory_updated' => $eligibleForStock,
                        ],
                        JSON_UNESCAPED_UNICODE
                    );
                    $action = 'record_donation';
                    $entityType = 'donation';
                    $aud = $conn->prepare(
                        'INSERT INTO audit_log (action, entity_type, entity_id, details, performed_by)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    if ($aud === false) {
                        throw new RuntimeException('Prepare failed.');
                    }
                    $aud->bind_param('ssiss', $action, $entityType, $donationPk, $details, $performedBy);
                    $aud->execute();
                    $aud->close();

                    $conn->commit();
                    header('Location: view_donor.php?id=' . $donorId . '&recorded=1');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    $lastDbError = $e->getMessage();
                    $showAlert = 'error';
                }
            }
        }
    }
}

$conn->close();
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Record Donation</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
      font-size: 28px;
      font-weight: 500;
      margin-bottom: 8px;
    }
    .sub {
      color: #666;
      margin-bottom: 24px;
      font-size: 14px;
    }
    .layout {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      align-items: start;
    }
    @media (max-width: 900px) {
      .layout { grid-template-columns: 1fr; }
    }
    .panel {
      background: #fff;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .panel h3 {
      margin: 0 0 12px 0;
      font-size: 16px;
      color: #b30000;
    }
    .search-container {
      display: flex;
      align-items: center;
      background-color: #fff;
      border-radius: 30px;
      padding: 5px 15px;
      border: 1px solid #e0e0e0;
      margin-bottom: 12px;
    }
    .search-container input {
      border: none;
      outline: none;
      padding: 8px 12px;
      flex: 1;
      font-size: 14px;
    }
    .donor-pick-wrap {
      max-height: 280px;
      overflow-y: auto;
      border: 1px solid #eee;
      border-radius: 6px;
    }
    table.pick {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    .pick th {
      background: #f4f4f4;
      color: #b30000;
      padding: 8px;
      text-align: left;
      position: sticky;
      top: 0;
    }
    .pick td {
      padding: 8px;
      border-top: 1px solid #f0f0f0;
      cursor: pointer;
    }
    .pick tr:hover td { background: #fff5f5; }
    .pick tr.selected td { background: #ffeaea; font-weight: 600; }
    .form-group { margin-bottom: 16px; }
    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 500;
      font-size: 14px;
    }
    .form-group input, .form-group select {
      width: 100%;
      max-width: 360px;
      padding: 10px 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
    }
    .hint {
      font-size: 12px;
      color: #888;
      margin-top: 4px;
    }
    .schema-warn {
      background: #fff3cd;
      border: 1px solid #ffc107;
      color: #856404;
      padding: 12px 16px;
      border-radius: 6px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    .button-wrapper {
      margin-top: 24px;
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
    }
    .button-wrapper button, .button-wrapper a.btn-link {
      padding: 12px 24px;
      border-radius: 30px;
      border: none;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .btn-primary {
      background: linear-gradient(135deg, #b30000, #800000);
      color: #fff;
      box-shadow: 0 4px 10px rgba(179, 0, 0, 0.3);
    }
    .btn-secondary {
      background: #fff;
      color: #b30000;
      border: 2px solid #b30000 !important;
    }
    #selectedDonorLabel {
      margin-top: 8px;
      font-size: 14px;
      color: #333;
    }
    #selectedDonorLabel strong { color: #b30000; }
  </style>
</head>
<body>

<h2><i class="fas fa-tint"></i> Record Donation</h2>
<p class="sub">Search and select a donor, then enter donation details. Inventory and the donor profile update automatically.</p>

<?php if (!$schemaOk): ?>
  <div class="schema-warn">
    <strong>Database update required.</strong> Run <code>schema_us02.sql</code> on the <code>bdms</code> database in phpMyAdmin (or MySQL client), then reload this page.
  </div>
<?php endif; ?>

<form method="post" action="" id="donationForm">
  <input type="hidden" name="donor_id" id="donor_id" value="<?php echo $preselectId > 0 ? (int) $preselectId : ''; ?>">

  <div class="layout">
    <div class="panel">
      <h3><i class="fas fa-search"></i> Select donor</h3>
      <div class="search-container">
        <input type="text" id="searchInput" placeholder="Search by name, email, blood type..." autocomplete="off">
        <i class="fas fa-search" style="color:#b30000;"></i>
      </div>
      <div class="donor-pick-wrap">
        <table class="pick" id="donorTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Type</th>
              <th>Contact</th>
            </tr>
          </thead>
          <tbody id="donorTableBody">
            <?php foreach ($donorsRows as $dr): ?>
              <tr data-id="<?php echo (int) $dr['id']; ?>"
                  data-name="<?php echo htmlspecialchars($dr['name'], ENT_QUOTES, 'UTF-8'); ?>">
                <td><?php echo (int) $dr['id']; ?></td>
                <td><?php echo htmlspecialchars($dr['name']); ?></td>
                <td><?php echo htmlspecialchars($dr['blood_type']); ?></td>
                <td><?php echo htmlspecialchars($dr['contact_number']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p id="selectedDonorLabel"><?php if ($preselectId <= 0): ?><span class="hint">Click a row to select a donor.</span><?php endif; ?></p>
    </div>

    <div class="panel">
      <h3><i class="fas fa-clipboard-list"></i> Donation details</h3>

      <div class="form-group">
        <label for="donation_date">Donation date</label>
        <input type="date" name="donation_date" id="donation_date" required
          value="<?php echo htmlspecialchars(bdms_today_ymd(), ENT_QUOTES, 'UTF-8'); ?>"
          max="<?php echo htmlspecialchars(bdms_today_ymd(), ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="form-group">
        <label for="blood_type">Blood type (verified)</label>
        <select name="blood_type" id="blood_type" required>
          <option value="" hidden>Select</option>
          <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
            <option value="<?php echo $bt; ?>"><?php echo $bt; ?></option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Defaults to the donor’s registered type when you select them.</p>
      </div>

      <div class="form-group">
        <label for="quantity_ml">Quantity (ml)</label>
        <input type="number" name="quantity_ml" id="quantity_ml" min="1" max="600" value="450" required>
      </div>

      <div class="form-group">
        <label for="eligibility_status">Eligibility status</label>
        <select name="eligibility_status" id="eligibility_status" required>
          <option value="Eligible">Eligible</option>
          <option value="Temporarily Deferred">Temporarily Deferred</option>
          <option value="Not Eligible">Not Eligible</option>
        </select>
      </div>

      <div class="button-wrapper">
        <button type="submit" class="btn-primary" <?php echo !$schemaOk ? 'disabled' : ''; ?>>
          <i class="fas fa-save"></i> Save donation
        </button>
        <a href="dashboard.php" class="btn-link btn-secondary"><i class="fas fa-home"></i> Dashboard</a>
        <a href="donors_list.php" class="btn-link btn-secondary"><i class="fas fa-users"></i> Donors list</a>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  const donorsMeta = <?php echo json_encode(
      array_map(static function ($r) {
          return [
              'id' => (int) $r['id'],
              'blood_type' => $r['blood_type'],
          ];
      }, $donorsRows),
      JSON_UNESCAPED_UNICODE
  ); ?>;

  const preselect = <?php echo (int) $preselectId; ?>;
  const hidden = document.getElementById('donor_id');
  const bloodSel = document.getElementById('blood_type');
  const label = document.getElementById('selectedDonorLabel');

  function setSelected(id, name) {
    hidden.value = id;
    const meta = donorsMeta.find(function (d) { return d.id === id; });
    if (meta && bloodSel) {
      const bt = String(meta.blood_type || '').trim();
      bloodSel.value = bt;
      if (bt && !Array.from(bloodSel.options).some(function (o) { return o.value === bt; })) {
        bloodSel.value = '';
      }
    }
    label.innerHTML = 'Selected: <strong>' + name.replace(/</g, '&lt;') + '</strong> (ID ' + id + ')';
    document.querySelectorAll('#donorTableBody tr').forEach(function (tr) {
      tr.classList.toggle('selected', parseInt(tr.getAttribute('data-id'), 10) === id);
    });
  }

  document.getElementById('donorTableBody').addEventListener('click', function (e) {
    const tr = e.target.closest('tr');
    if (!tr) return;
    const id = parseInt(tr.getAttribute('data-id'), 10);
    const name = tr.getAttribute('data-name') || '';
    setSelected(id, name);
  });

  document.getElementById('searchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#donorTableBody tr').forEach(function (tr) {
      const t = tr.innerText.toLowerCase();
      tr.style.display = !q || t.includes(q) ? '' : 'none';
    });
  });

  if (preselect > 0) {
    const row = document.querySelector('#donorTableBody tr[data-id="' + preselect + '"]');
    if (row) {
      setSelected(preselect, row.getAttribute('data-name') || '');
    }
  }

  let allowSubmit = false;
  document.getElementById('donationForm').addEventListener('submit', function (e) {
    if (allowSubmit) {
      return;
    }

    if (!hidden.value) {
      e.preventDefault();
      Swal.fire({
        icon: 'warning',
        title: 'Select a donor',
        text: 'Please choose a donor from the list.',
        confirmButtonColor: '#b30000'
      });
      return;
    }

    e.preventDefault();
    const form = this;

    if (typeof Swal === 'undefined') {
      const shouldSubmit = window.confirm('Are you sure you want to save this donation?');
      if (shouldSubmit) {
        allowSubmit = true;
        form.submit();
      }
      return;
    }

    Swal.fire({
      icon: 'question',
      title: 'Save donation record?',
      text: 'Please confirm before adding this donation data.',
      showCancelButton: true,
      confirmButtonText: 'Yes, save',
      cancelButtonText: 'No, cancel',
      confirmButtonColor: '#b30000',
      cancelButtonColor: '#6c757d',
      reverseButtons: true
    }).then(function (result) {
      if (result.isConfirmed) {
        allowSubmit = true;
        form.submit();
      }
    });
  });
})();

<?php if ($showAlert === 'validation' && $validationErrors): ?>
Swal.fire({
  icon: 'error',
  title: 'Check the form',
  html: <?php echo json_encode('<ul style="text-align:left">' . implode('', array_map(static function ($e) {
      return '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
  }, $validationErrors)) . '</ul>'); ?>,
  confirmButtonColor: '#b30000'
});
<?php elseif ($showAlert === 'schema'): ?>
Swal.fire({
  icon: 'warning',
  title: 'Database update required',
  html: <?php echo json_encode('<ul style="text-align:left">' . implode('', array_map(static function ($e) {
      return '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
  }, $schemaIssues)) . '</ul>'); ?>,
  confirmButtonColor: '#b30000'
});
<?php elseif ($showAlert === 'error'): ?>
Swal.fire({
  icon: 'error',
  title: 'Could not save',
  html: <?php echo json_encode(
      'Please try again.<br><br><strong>Details:</strong><br><code style="white-space:pre-wrap">' .
      htmlspecialchars($lastDbError ?: 'Unknown database error', ENT_QUOTES, 'UTF-8') .
      '</code>',
      JSON_UNESCAPED_UNICODE
  ); ?>,
  confirmButtonColor: '#b30000'
});
<?php endif; ?>
</script>
</body>
</html>

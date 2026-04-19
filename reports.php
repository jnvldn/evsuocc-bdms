<?php
declare(strict_types=1);

require_once __DIR__ . '/require_admin.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reports_helpers.php';

$filters = reports_parse_filters($_GET);

$donorRows = [];
$donorSummary = ['total' => 0, 'by_blood' => []];
$invData = [
    'stock_by_type' => [],
    'donations_period' => [],
    'totals' => ['available_ml' => 0, 'expired_ml' => 0],
];

if ($filters['report'] === 'donor') {
    $donorRows = reports_fetch_donor_rows($conn, $filters);
    $donorSummary = reports_donor_summary($donorRows);
} else {
    $invData = reports_fetch_inventory_data($conn, $filters);
}

$conn->close();

$csvUrl = 'reports_export.php?' . http_build_query(array_merge($filters, ['format' => 'csv']));
$pdfUrl = 'reports_export.php?' . http_build_query(array_merge($filters, ['format' => 'pdf']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Generate Report</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      font-family: 'Montserrat', sans-serif;
      background: #f5f5f5;
      color: #4d4d4d;
      padding: 40px 40px 120px;
      margin: 0;
    }
    h2 {
      color: #b30000;
      font-size: 28px;
      font-weight: 500;
      margin-bottom: 8px;
    }
    .sub { color: #666; font-size: 14px; margin-bottom: 24px; }
    form.filters {
      background: #fff;
      padding: 20px 24px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      margin-bottom: 24px;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 16px 20px;
      align-items: end;
    }
    form.filters label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #b30000;
      margin-bottom: 6px;
    }
    form.filters input, form.filters select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      box-sizing: border-box;
    }
    .form-actions {
      grid-column: 1 / -1;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: center;
      margin-top: 8px;
    }
    .btn {
      padding: 10px 20px;
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
      box-shadow: 0 4px 10px rgba(179, 0, 0, 0.25);
    }
    .btn-outline {
      background: #fff;
      color: #b30000;
      border: 2px solid #b30000;
    }
    .summary {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      margin-bottom: 20px;
    }
    .summary-card {
      background: #ffeaea;
      border: 2px solid #b30000;
      border-radius: 10px;
      padding: 16px 20px;
      min-width: 160px;
    }
    .summary-card strong { display: block; color: #8b0000; font-size: 13px; }
    .summary-card span { font-size: 22px; font-weight: 600; color: #333; }
    .table-wrap {
      overflow-x: auto;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 640px;
    }
    thead th {
      background: #f4f4f4;
      color: #b30000;
      padding: 12px 10px;
      font-size: 13px;
      text-align: left;
      border-bottom: 2px solid #e0e0e0;
    }
    tbody td {
      padding: 10px;
      font-size: 13px;
      border-bottom: 1px solid #eee;
    }
    tbody tr:nth-child(even) { background: #fafafa; }
    .button-wrapper {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      padding: 15px 0;
      background: #fff;
      display: flex;
      justify-content: center;
      gap: 16px;
      flex-wrap: wrap;
      box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
      z-index: 1000;
    }
    .button-wrapper a {
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 24px;
      background: linear-gradient(135deg, #b30000, #800000);
      color: #fff;
      border-radius: 30px;
      font-size: 14px;
      font-weight: 500;
    }
    h3 { color: #b30000; font-size: 18px; margin: 24px 0 12px; }
    .muted { color: #888; font-size: 13px; margin-bottom: 16px; }
  </style>
</head>
<body>

  <h2><i class="fas fa-file-alt"></i> Generate report</h2>
  <p class="sub">Choose report type, set filters, review the summary, then export to CSV or PDF.</p>

  <form class="filters" method="get" action="reports.php">
    <div>
      <label for="report">Report type</label>
      <select name="report" id="report">
        <option value="donor" <?php echo $filters['report'] === 'donor' ? 'selected' : ''; ?>>Donor activity</option>
        <option value="inventory" <?php echo $filters['report'] === 'inventory' ? 'selected' : ''; ?>>Blood inventory</option>
      </select>
    </div>
    <div>
      <label for="date_from">Date from</label>
      <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" required>
    </div>
    <div>
      <label for="date_to">Date to</label>
      <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" required>
    </div>
    <div>
      <label for="blood_type">Blood type</label>
      <select name="blood_type" id="blood_type">
        <option value="">All types</option>
        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
          <option value="<?php echo $bt; ?>" <?php echo $filters['blood_type'] === $bt ? 'selected' : ''; ?>><?php echo $bt; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="classification">Donor classification</label>
      <select name="classification" id="classification">
        <option value="">All</option>
        <?php foreach (['Student','Staff','Public'] as $c): ?>
          <option value="<?php echo $c; ?>" <?php echo $filters['classification'] === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><i class="fas fa-sync"></i> Apply filters</button>
      <a class="btn btn-outline" href="<?php echo htmlspecialchars($csvUrl); ?>"><i class="fas fa-file-csv"></i> Download CSV</a>
      <a class="btn btn-outline" href="<?php echo htmlspecialchars($pdfUrl); ?>"><i class="fas fa-file-pdf"></i> Download PDF</a>
    </div>
  </form>

  <?php if ($filters['report'] === 'donor'): ?>
    <div class="summary">
      <div class="summary-card">
        <strong>Donors in scope</strong>
        <span><?php echo (int) $donorSummary['total']; ?></span>
      </div>
      <?php foreach ($donorSummary['by_blood'] as $bt => $cnt): ?>
        <div class="summary-card">
          <strong><?php echo htmlspecialchars($bt); ?></strong>
          <span><?php echo (int) $cnt; ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="muted">Donors with collection or donation activity between the selected dates (and matching filters).</p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Contact</th>
            <th>Classification</th>
            <th>Blood</th>
            <th>Collection</th>
            <th># Donations</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($donorRows) === 0): ?>
            <tr><td colspan="7">No records match the filters.</td></tr>
          <?php else: ?>
            <?php foreach ($donorRows as $r): ?>
              <tr>
                <td><?php echo htmlspecialchars((string) $r['name']); ?></td>
                <td><?php echo htmlspecialchars((string) $r['contact_number']); ?></td>
                <td><?php echo htmlspecialchars((string) $r['classification']); ?></td>
                <td><?php echo htmlspecialchars((string) $r['blood_type']); ?></td>
                <td><?php echo htmlspecialchars((string) $r['collection_date']); ?></td>
                <td><?php echo (int) ($r['number_of_donations'] ?? 0); ?></td>
                <td><?php echo htmlspecialchars((string) ($r['donation_status'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php else: ?>
    <div class="summary">
      <div class="summary-card">
        <strong>Available stock (ml)</strong>
        <span><?php echo (int) $invData['totals']['available_ml']; ?></span>
      </div>
      <div class="summary-card">
        <strong>Expired (ml)</strong>
        <span><?php echo (int) $invData['totals']['expired_ml']; ?></span>
      </div>
    </div>

    <h3><i class="fas fa-layer-group"></i> Current stock by blood type</h3>
    <p class="muted">Totals from donor records (Active / Expired quantities). Respects blood type and classification filters.</p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Blood type</th>
            <th>Available (ml)</th>
            <th>Expired (ml)</th>
            <th>Donor rows</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($invData['stock_by_type']) === 0): ?>
            <tr><td colspan="4">No inventory rows for these filters.</td></tr>
          <?php else: ?>
            <?php foreach ($invData['stock_by_type'] as $s): ?>
              <tr>
                <td><?php echo htmlspecialchars($s['blood_type']); ?></td>
                <td><?php echo (int) $s['ml_available']; ?></td>
                <td><?php echo (int) $s['ml_expired']; ?></td>
                <td><?php echo (int) $s['donor_rows']; ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <h3><i class="fas fa-calendar-check"></i> Donations recorded in date range</h3>
    <p class="muted">From the donations register, if available (same date and filter scope).</p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Blood type</th>
            <th>Total volume (ml)</th>
            <th>Donation events</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($invData['donations_period']) === 0): ?>
            <tr><td colspan="3">No donation records in range (or donations table not installed).</td></tr>
          <?php else: ?>
            <?php foreach ($invData['donations_period'] as $d): ?>
              <tr>
                <td><?php echo htmlspecialchars($d['blood_type']); ?></td>
                <td><?php echo (int) $d['total_ml']; ?></td>
                <td><?php echo (int) $d['donation_count']; ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="button-wrapper">
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
  </div>

</body>
</html>

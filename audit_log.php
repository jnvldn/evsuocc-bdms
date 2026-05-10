<?php
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: dashboard.php');
    exit;
}

/** User-facing label for the Action column (stored value stays the machine key). */
function audit_log_action_display(string $action): string
{
    static $labels = [
        'add_donor' => 'New donor registered',
        'update_donor' => 'Donor Info Update',
        'delete_donor' => 'Donor record deleted',
        'record_donation' => 'Blood donation recorded',
        'login_success' => 'Login successful',
        'login_failure' => 'Login attempt failed',
    ];

    if (isset($labels[$action])) {
        return $labels[$action];
    }

    $t = trim($action);
    if ($t === '') {
        return '—';
    }

    return ucwords(str_replace('_', ' ', $t));
}

/**
 * Turn stored JSON or plain text into readable lines for the Details column.
 */
function audit_log_format_details_cell(?string $raw, string $action): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
    }

    $lines = [];
    $known = [];

    if ($action === 'record_donation' || isset($decoded['donation_id'])) {
        if (isset($decoded['donation_id'])) {
            $lines[] = 'Donation ID: ' . (int) $decoded['donation_id'];
            $known['donation_id'] = true;
        }
        if (isset($decoded['donor_id'])) {
            $lines[] = 'Donor ID: ' . (int) $decoded['donor_id'];
            $known['donor_id'] = true;
        }
        if (isset($decoded['quantity_ml'])) {
            $lines[] = 'Quantity: ' . (int) $decoded['quantity_ml'] . ' mL';
            $known['quantity_ml'] = true;
        }
        if (isset($decoded['blood_type'])) {
            $lines[] = 'Blood type: ' . (string) $decoded['blood_type'];
            $known['blood_type'] = true;
        }
        if (isset($decoded['donation_date'])) {
            $lines[] = 'Donation date: ' . (string) $decoded['donation_date'];
            $known['donation_date'] = true;
        }
        if (array_key_exists('inventory_updated', $decoded)) {
            $iu = $decoded['inventory_updated'];
            $lines[] = 'Inventory updated: ' . ($iu === true || $iu === 1 || $iu === '1' ? 'Yes' : 'No');
            $known['inventory_updated'] = true;
        }
    } elseif (in_array($action, ['add_donor', 'update_donor', 'delete_donor'], true)) {
        $labels = [
            'donor_id' => 'Donor ID',
            'name' => 'Name',
            'blood_type' => 'Blood type',
            'email' => 'Email',
            'classification' => 'Classification',
        ];
        foreach (['donor_id', 'name', 'blood_type', 'email', 'classification'] as $key) {
            if (!isset($decoded[$key])) {
                continue;
            }
            $known[$key] = true;
            $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
            $lines[] = $label . ': ' . (string) $decoded[$key];
        }
    }

    foreach ($decoded as $key => $value) {
        if (isset($known[(string) $key])) {
            continue;
        }
        $label = ucfirst(str_replace('_', ' ', (string) $key));
        if (is_bool($value)) {
            $lines[] = $label . ': ' . ($value ? 'Yes' : 'No');
        } elseif (is_scalar($value)) {
            $lines[] = $label . ': ' . $value;
        }
    }

    if ($lines === []) {
        return htmlspecialchars(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8');
}

$tableOk = false;
$rows = [];

$chk = $conn->query("SHOW TABLES LIKE 'audit_log'");
if ($chk && $chk->num_rows > 0) {
    $tableOk = true;
    $staff_users_ok = false;
    $su = $conn->query("SHOW TABLES LIKE 'staff_users'");
    if ($su && $su->num_rows > 0) {
        $staff_users_ok = true;
    }
    if ($staff_users_ok) {
        $res = $conn->query(
            'SELECT a.id, a.action, a.entity_type, a.entity_id, a.details, a.performed_by, a.created_at,
                    s.display_name AS performer_display_name
             FROM audit_log a
             LEFT JOIN staff_users s ON s.username = a.performed_by
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 300'
        );
    } else {
        $res = $conn->query(
            'SELECT id, action, entity_type, entity_id, details, performed_by, created_at,
                    NULL AS performer_display_name
             FROM audit_log
             ORDER BY created_at DESC, id DESC
             LIMIT 300'
        );
    }
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Log</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      font-family: 'Montserrat', sans-serif;
      background: #f5f5f5;
      color: #4d4d4d;
      padding: 40px 40px 100px;
      margin: 0;
    }
    h2 {
      color: #b30000;
      font-size: 28px;
      font-weight: 500;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .sub {
      color: #666;
      font-size: 14px;
      margin-bottom: 24px;
    }
    .warn {
      background: #fff3cd;
      border: 1px solid #ffc107;
      color: #856404;
      padding: 14px 18px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    .audit-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
      padding: 12px 14px;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .audit-search-wrap {
      flex: 1 1 240px;
      position: relative;
      display: flex;
      align-items: center;
      min-width: 0;
    }
    .audit-search-wrap .fa-magnifying-glass {
      position: absolute;
      left: 14px;
      color: #888;
      pointer-events: none;
      font-size: 14px;
    }
    .audit-search-wrap input[type="search"] {
      width: 100%;
      box-sizing: border-box;
      padding: 10px 14px 10px 40px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-family: inherit;
      font-size: 14px;
      background: #fafafa;
    }
    .audit-search-wrap input[type="search"]:focus {
      outline: none;
      border-color: #b30000;
      background-color: #fff;
    }
    .audit-search-meta {
      font-size: 13px;
      color: #666;
      white-space: nowrap;
    }
    .table-wrap {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.08);
      display: flex;
      flex-direction: column;
      max-height: min(70vh, calc(100vh - 260px));
      min-height: 120px;
    }
    .table-scroll {
      overflow: auto;
      flex: 1 1 auto;
      -webkit-overflow-scrolling: touch;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 560px;
    }
    thead th {
      background: #f4f4f4;
      color: #b30000;
      padding: 12px 10px;
      font-size: 13px;
      text-align: left;
      border-bottom: 2px solid #e0e0e0;
      white-space: nowrap;
      position: sticky;
      top: 0;
      z-index: 2;
      box-shadow: 0 1px 0 #e0e0e0;
    }
    tbody td {
      padding: 10px;
      font-size: 13px;
      border-bottom: 1px solid #eee;
      vertical-align: top;
    }
    tbody tr:nth-child(even) {
      background: #fafafa;
    }
    .mono {
      font-family: ui-monospace, Consolas, monospace;
      font-size: 12px;
      word-break: break-word;
      max-width: 420px;
    }
    .audit-details-plain {
      white-space: pre-line;
      font-family: 'Montserrat', sans-serif;
      font-size: 13px;
      line-height: 1.5;
      word-break: break-word;
      max-width: 440px;
      color: #333;
    }
    .action-tag {
      display: inline-block;
      background: #ffeaea;
      color: #8b0000;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 600;
    }
    .button-wrapper {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      padding: 15px 0;
      background: #fff;
      display: flex;
      justify-content: center;
      gap: 20px;
      box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
      z-index: 1000;
    }
    .button-wrapper a {
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 28px;
      background: linear-gradient(135deg, #b30000, #800000);
      color: #fff;
      border-radius: 30px;
      font-size: 14px;
      font-weight: 500;
      box-shadow: 0 4px 10px rgba(179, 0, 0, 0.3);
    }
    .button-wrapper a:hover {
      filter: brightness(1.05);
    }
    .empty {
      padding: 40px;
      text-align: center;
      color: #888;
    }
  </style>
</head>
<body>

  <h2><i class="fas fa-clipboard-list"></i> Audit log</h2>
  <p class="sub">Read-only record of system actions (latest 300 entries). Newest first. Use the search box to filter rows.</p>

  <?php if (!$tableOk): ?>
    <div class="warn">
      <strong>Table missing.</strong> Run <code>schema_us02.sql</code> on the <code>bdms</code> database to create <code>audit_log</code>.
    </div>
  <?php elseif (count($rows) === 0): ?>
    <div class="table-wrap">
      <p class="empty">No audit entries yet. Actions such as recording donations will appear here.</p>
    </div>
  <?php else: ?>
    <div class="audit-toolbar">
      <div class="audit-search-wrap">
        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" id="audit-log-search" name="q" placeholder="Search date, action, entity, user, details…" autocomplete="off" spellcheck="false" aria-label="Search audit log" aria-describedby="audit-log-count">
      </div>
      <span id="audit-log-count" class="audit-search-meta" aria-live="polite"></span>
    </div>
    <div class="table-wrap">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>When</th>
              <th>Action</th>
              <th>Entity</th>
              <th>By</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody id="audit-log-tbody">
          <?php foreach ($rows as $row): ?>
            <tr>
              <td class="mono"><?php echo htmlspecialchars((string) $row['created_at']); ?></td>
              <td><span class="action-tag" title="<?php echo htmlspecialchars((string) $row['action'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(audit_log_action_display((string) $row['action']), ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><?php echo htmlspecialchars((string) $row['entity_type']); ?></td>
              <td><?php
                  $name = trim((string) ($row['performer_display_name'] ?? ''));
                  $fallback = trim((string) ($row['performed_by'] ?? ''));
                  $show = $name !== '' ? $name : $fallback;
                  echo $show !== '' ? htmlspecialchars($show, ENT_QUOTES, 'UTF-8') : '—';
              ?></td>
              <td><span class="audit-details-plain"><?php echo audit_log_format_details_cell(
                  isset($row['details']) ? (string) $row['details'] : null,
                  (string) ($row['action'] ?? '')
              ); ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <script>
    (function () {
      var input = document.getElementById('audit-log-search');
      var tbody = document.getElementById('audit-log-tbody');
      var countEl = document.getElementById('audit-log-count');
      if (!input || !tbody) return;
      var rows = tbody.querySelectorAll('tr');

      function updateCount(visible) {
        if (!countEl) return;
        var total = rows.length;
        countEl.textContent = visible === total
          ? total + ' entries'
          : visible + ' of ' + total + ' shown';
      }

      function filter() {
        var q = input.value.replace(/\s+/g, ' ').trim().toLowerCase();
        var visible = 0;
        for (var i = 0; i < rows.length; i++) {
          var tr = rows[i];
          var text = tr.textContent || '';
          var match = !q || text.toLowerCase().indexOf(q) !== -1;
          tr.style.display = match ? '' : 'none';
          if (match) visible++;
        }
        updateCount(visible);
      }

      input.addEventListener('input', filter);
      input.addEventListener('search', filter);
      updateCount(rows.length);
    })();
    </script>
  <?php endif; ?>

  <div class="button-wrapper">
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>

</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db.php';

$tableOk = false;
$rows = [];

$chk = $conn->query("SHOW TABLES LIKE 'audit_log'");
if ($chk && $chk->num_rows > 0) {
    $tableOk = true;
    $res = $conn->query(
        'SELECT id, action, entity_type, entity_id, details, performed_by, created_at
         FROM audit_log
         ORDER BY created_at DESC, id DESC
         LIMIT 300'
    );
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
    .table-wrap {
      overflow-x: auto;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 720px;
    }
    thead th {
      background: #f4f4f4;
      color: #b30000;
      padding: 12px 10px;
      font-size: 13px;
      text-align: left;
      border-bottom: 2px solid #e0e0e0;
      white-space: nowrap;
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
  <p class="sub">Read-only record of system actions (latest 300 entries). Newest first.</p>

  <?php if (!$tableOk): ?>
    <div class="warn">
      <strong>Table missing.</strong> Run <code>schema_us02.sql</code> on the <code>bdms</code> database to create <code>audit_log</code>.
    </div>
  <?php elseif (count($rows) === 0): ?>
    <div class="table-wrap">
      <p class="empty">No audit entries yet. Actions such as recording donations will appear here.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>When</th>
            <th>Action</th>
            <th>Entity</th>
            <th>ID</th>
            <th>By</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td class="mono"><?php echo htmlspecialchars((string) $row['created_at']); ?></td>
              <td><span class="action-tag"><?php echo htmlspecialchars((string) $row['action']); ?></span></td>
              <td><?php echo htmlspecialchars((string) $row['entity_type']); ?></td>
              <td><?php echo $row['entity_id'] !== null ? (int) $row['entity_id'] : '—'; ?></td>
              <td><?php echo htmlspecialchars((string) ($row['performed_by'] ?? '')); ?></td>
              <td class="mono"><?php
                  $d = (string) ($row['details'] ?? '');
                  echo htmlspecialchars($d);
              ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="button-wrapper">
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
  </div>

</body>
</html>

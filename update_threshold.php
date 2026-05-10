<?php
/**
 * Update low-inventory threshold values per blood type.
 * This page lets users select a blood type and set a new threshold in mL.
 */
declare(strict_types=1);

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db.php';

$messages = [];
$errors = [];

$known_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

$create_table_sql = "
    CREATE TABLE IF NOT EXISTS blood_thresholds (
        blood_type VARCHAR(10) NOT NULL,
        threshold_ml INT NOT NULL DEFAULT 500,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (blood_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";
if (!$conn->query($create_table_sql)) {
    $errors[] = 'Unable to prepare threshold storage right now. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors === []) {
    $selected_blood_type = isset($_POST['blood_type']) ? trim((string) $_POST['blood_type']) : '';
    $threshold_raw = isset($_POST['threshold_ml']) ? trim((string) $_POST['threshold_ml']) : '';

    if (!in_array($selected_blood_type, $known_blood_types, true)) {
        $errors[] = 'Please select a valid blood type.';
    }

    if ($threshold_raw === '' || filter_var($threshold_raw, FILTER_VALIDATE_INT) === false) {
        $errors[] = 'Threshold must be a whole number.';
    } else {
        $threshold_ml = (int) $threshold_raw;
        if ($threshold_ml < 0 || $threshold_ml > 100000) {
            $errors[] = 'Threshold must be between 0 and 100000 mL.';
        }
    }

    if ($errors === []) {
        $upsert_sql = "
            INSERT INTO blood_thresholds (blood_type, threshold_ml)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE threshold_ml = VALUES(threshold_ml)
        ";
        $stmt = $conn->prepare($upsert_sql);
        if (!$stmt) {
            $errors[] = 'Failed to prepare update statement. Please try again.';
        } else {
            $stmt->bind_param('si', $selected_blood_type, $threshold_ml);
            if ($stmt->execute()) {
                $messages[] = 'Threshold updated successfully for ' . $selected_blood_type . '.';
            } else {
                $errors[] = 'Failed to update threshold. Please try again.';
            }
            $stmt->close();
        }
    }
}

$threshold_map = [];
$res_thresholds = $conn->query("SELECT blood_type, threshold_ml FROM blood_thresholds");
if ($res_thresholds) {
    while ($row = $res_thresholds->fetch_assoc()) {
        $threshold_map[(string) $row['blood_type']] = (int) $row['threshold_ml'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Update Blood Threshold</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      font-family: 'Montserrat', sans-serif;
      background: #f5f5f5;
      color: #4d4d4d;
      padding: 40px;
      margin: 0;
    }
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 900px;
      margin: 0 auto 24px auto;
    }
    .page-header h2 {
      color: #b30000;
      margin: 0;
      font-weight: 500;
    }
    .back-link {
      text-decoration: none;
      color: #b30000;
      font-weight: 600;
    }
    .back-link:hover {
      text-decoration: underline;
    }
    .card {
      max-width: 900px;
      margin: 0 auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      padding: 24px;
    }
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr auto;
      gap: 12px;
      align-items: end;
    }
    label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: #6b0000;
    }
    select,
    input[type="number"] {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      box-sizing: border-box;
    }
    button[type="submit"] {
      padding: 11px 18px;
      border: none;
      border-radius: 8px;
      background: linear-gradient(135deg, #b30000, #800000);
      color: #fff;
      font-weight: 600;
      cursor: pointer;
    }
    button[type="submit"]:hover {
      opacity: 0.92;
    }
    .notice {
      margin: 0 auto 16px auto;
      max-width: 900px;
      border-radius: 8px;
      padding: 12px 14px;
      font-size: 14px;
    }
    .notice.success {
      background: #e9f8ef;
      border: 1px solid #b8e6c6;
      color: #0f6b2c;
    }
    .notice.error {
      background: #fdecec;
      border: 1px solid #f4b4b4;
      color: #991b1b;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 24px;
    }
    th,
    td {
      border: 1px solid #e6e6e6;
      padding: 10px;
      text-align: center;
      font-size: 14px;
    }
    th {
      background: #fff5f5;
      color: #8b0000;
    }
    @media (max-width: 768px) {
      body {
        padding: 20px;
      }
      .form-grid {
        grid-template-columns: 1fr;
      }
      button[type="submit"] {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="page-header">
    <h2><i class="fas fa-sliders-h"></i> Update Blood Type Threshold</h2>
    <a class="back-link" href="blood_inventory.php"><i class="fas fa-arrow-left"></i> Back to Inventory</a>
  </div>

  <?php foreach ($messages as $message) : ?>
    <div class="notice success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endforeach; ?>

  <?php foreach ($errors as $error) : ?>
    <div class="notice error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endforeach; ?>

  <div class="card">
    <form method="post" action="update_threshold.php">
      <div class="form-grid">
        <div>
          <label for="blood_type">Select blood type</label>
          <select id="blood_type" name="blood_type" required>
            <option value="">-- Choose blood type --</option>
            <?php foreach ($known_blood_types as $blood_type) : ?>
              <?php $selected = (isset($_POST['blood_type']) && $_POST['blood_type'] === $blood_type) ? 'selected' : ''; ?>
              <option value="<?php echo htmlspecialchars($blood_type, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>>
                <?php echo htmlspecialchars($blood_type, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="threshold_ml">Threshold (mL)</label>
          <input
            id="threshold_ml"
            name="threshold_ml"
            type="number"
            min="0"
            max="100000"
            required
            value="<?php echo isset($_POST['threshold_ml']) ? htmlspecialchars((string) $_POST['threshold_ml'], ENT_QUOTES, 'UTF-8') : ''; ?>"
          >
        </div>
        <div>
          <button type="submit"><i class="fas fa-save"></i> Save</button>
        </div>
      </div>
    </form>

    <table>
      <thead>
        <tr>
          <th>Blood Type</th>
          <th>Current Threshold (mL)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($known_blood_types as $blood_type) : ?>
          <tr>
            <td><?php echo htmlspecialchars($blood_type, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo isset($threshold_map[$blood_type]) ? (int) $threshold_map[$blood_type] : 500; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>

<?php
$conn->close();
?>

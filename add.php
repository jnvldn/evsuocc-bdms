<?php
/**
 * Register donors (staff may add; only administrators may delete donor records).
 */
ob_start();
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bdms_profile_bar.php';
require_once __DIR__ . '/bdms_audit_log_helpers.php';
require_once __DIR__ . "/donor_helpers.php";

$showAlert = "";

$validationErrors = [];
$duplicateDonorId = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $check = donor_validate_registration_inputs($_POST);
  if (!$check["ok"]) {
    $validationErrors = $check["errors"];
    $showAlert = "validation";
  } else {
    $name = trim((string) $_POST["name"]);
    $birthdate = trim((string) $_POST["birthdate"]);
    $address = trim((string) $_POST["address"]);
    $blood_type = (string) $_POST["blood_type"];
    $civil_status = (string) $_POST["civil_status"];
    $donation_history = (string) $_POST["donation_history"];
    $classification = (string) $_POST["classification"];
    $contact_number = (string) $_POST["contact_number"];
    $gender = (string) $_POST["gender"];
    $blood_quantity = (int) $_POST["blood_quantity"];
    $collection_date = trim((string) $_POST["collection_date"]);
    $email = trim((string) $_POST["email"]);
    $donation_type = (string) $_POST["donation_type"];
    $donation_location = (string) $_POST["donation_location"];

   $birthdateObj = DateTimeImmutable::createFromFormat("Y-m-d", $birthdate);
    $birthYmd = $birthdateObj !== false ? $birthdateObj->format("Y-m-d") : $birthdate;
    $current_date = new DateTimeImmutable();
    $age = $birthdateObj !== false ? $current_date->diff($birthdateObj)->y : 0;


  $dupId = donor_find_duplicate_id($conn, $email, $contact_number, $birthYmd);
    if ($dupId !== null) {
      $showAlert = "duplicate";
      $duplicateDonorId = $dupId;
    } else {
      $donation_status = "Active";
      $sql = "INSERT INTO donors (
        name, birthdate, address, blood_type, civil_status, donation_history,
        classification, contact_number, gender, blood_quantity, collection_date,
        email, age, donation_type, donation_location, donation_status
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

  $stmt = $conn->prepare($sql);
      if ($stmt === false) {
        $showAlert = "error";
      } else {
        $stmt->bind_param(
          "sssssssssississs",
          $name,
          $birthYmd,
          $address,
          $blood_type,
          $civil_status,
          $donation_history,
          $classification,
          $contact_number,
          $gender,
          $blood_quantity,
          $collection_date,
          $email,
          $age,
          $donation_type,
          $donation_location,
          $donation_status
        );
        if ($stmt->execute()) {
          $newDonorId = (int) $conn->insert_id;
          bdms_audit_log_insert($conn, 'add_donor', 'New donor', $newDonorId, [
            'donor_id' => $newDonorId,
            'name' => $name,
            'blood_type' => $blood_type,
            'email' => $email,
            'classification' => $classification,
          ]);
          $showAlert = "success";
        } else {
          $showAlert = "error";
        }
        $stmt->close();
      }
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
  <title>Add Donor</title>
  <?php bdms_profile_bar_print_styles(); ?>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Montserrat', Arial, sans-serif;
      background: #ffeaea;
      font-size: 14px;
      min-height: 100vh;
      overflow-x: hidden;
      overflow-y: auto;
      padding-top: 0;
    }

    /* Top navbar: title + profile + all actions */
    .add-navbar {
      position: sticky;
      top: 0;
      z-index: 2000;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px 16px;
      padding: 10px 16px;
      background: linear-gradient(135deg, #8b0000, #b30000);
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
    }

    .add-navbar-left {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px 20px;
      min-width: 0;
      flex: 1 1 200px;
    }

    .add-navbar-title {
      color: #fff;
      font-size: clamp(1rem, 2.5vw, 1.25rem);
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100%;
    }

    .add-navbar-title i {
      margin-right: 8px;
    }

    .add-navbar .bdms-profile-bar--light {
      max-width: 260px;
      flex-shrink: 0;
    }

    .add-nav-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      flex: 1 1 180px;
    }

    .add-nav-actions button,
    .add-nav-actions a.nav-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      border: none;
      text-decoration: none;
      white-space: nowrap;
      transition: filter 0.2s, transform 0.15s;
      font-family: inherit;
    }

    .add-nav-actions button:hover,
    .add-nav-actions a.nav-btn:hover {
      filter: brightness(1.08);
      transform: translateY(-1px);
    }

    .nav-btn-primary {
      background: #fff;
      color: #b30000;
    }

    .nav-btn-secondary {
      background: rgba(255, 255, 255, 0.2);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.45) !important;
    }

    .nav-btn-outline {
      background: transparent;
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.55) !important;
    }

    .add-main {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px 16px 32px;
    }

    form#form {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
    }

    .form-group,
    .form-group-half,
    .form-group-full {
      display: flex;
      flex-direction: column;
    }

    .form-group {
      flex: 1 1 calc(33.333% - 16px);
      min-width: 180px;
    }

    .form-group-half {
      flex: 1 1 calc(50% - 16px);
      min-width: 220px;
    }

    .form-group-full {
      flex: 1 1 100%;
    }

    label {
      font-weight: bold;
      margin-bottom: 5px;
      font-size: 13px;
      color: #4d0000;
    }

    input,
    select {
      padding: 10px 12px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
      width: 100%;
      max-width: 100%;
    }

    @media (max-width: 900px) {
      .form-group,
      .form-group-half {
        flex: 1 1 calc(50% - 12px);
      }
    }

    @media (max-width: 600px) {
      .form-group,
      .form-group-half,
      .form-group-full {
        flex: 1 1 100%;
        min-width: 100%;
      }

      .add-navbar {
        padding: 10px 12px;
      }

      .add-nav-actions {
        justify-content: flex-start;
        width: 100%;
      }

      .add-nav-actions button,
      .add-nav-actions a.nav-btn {
        flex: 1 1 auto;
        min-width: calc(50% - 6px);
        white-space: normal;
        text-align: center;
      }
    }
  </style>
</head>
<body>

  <header class="add-navbar" role="banner">
    <div class="add-navbar-left">
      <div class="add-navbar-title"><i class="fas fa-user-plus"></i> Add New Donor</div>
      <?php bdms_profile_bar_render(true); ?>
    </div>
    <nav class="add-nav-actions" aria-label="Form actions">
      <button type="submit" form="form" class="nav-btn-primary"><i class="fas fa-plus"></i> Add Donor</button>
      <button type="button" class="nav-btn-secondary" onclick="clearForm();"><i class="fas fa-eraser"></i> Clear</button>
      <a class="nav-btn nav-btn-outline" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a class="nav-btn nav-btn-outline" href="donors_list.php"><i class="fas fa-list"></i> Donors List</a>
    </nav>
  </header>

  <main class="add-main">
  <form id="form" method="POST" action="">

    <div class="form-group">
      <label>Full Name:</label>
      <input type="text" name="name" required>
    </div>

    <div class="form-group">
      <label>Birth Date:</label>
      <input type="date" name="birthdate" required>
    </div>

    <div class="form-group">
      <label>Blood Type:</label>
      <select name="blood_type" required>
        <option value="" hidden>Select</option>
        <option value="A+">A+</option>
        <option value="A-">A-</option>
        <option value="B+">B+</option>
        <option value="B-">B-</option>
        <option value="AB+">AB+</option>
        <option value="AB-">AB-</option>
        <option value="O+">O+</option>
        <option value="O-">O-</option>
      </select>
    </div>

    <div class="form-group">
      <label>Civil Status:</label>
      <select name="civil_status" required>
        <option value="" hidden>Select</option>
        <option value="Single">Single</option>
        <option value="Married">Married</option>
        <option value="Widowed">Widowed</option>
      </select>
    </div>

    <div class="form-group">
      <label>History of Blood Donation:</label>
      <select name="donation_history" required>
        <option value="" hidden>Select</option>
        <option value="First Time">First Time</option>
        <option value="Regular Donor">Regular Donor</option>
        <option value="Occasional Donor">Occasional Donor</option>
      </select>
    </div>

    <div class="form-group">
      <label>Classification:</label>
      <select name="classification" required>
        <option value="" hidden>Select</option>
        <option value="Student">Student</option>
        <option value="Staff">Staff</option>
        <option value="Public">Public</option>
      </select>
    </div>

    <div class="form-group-half">
      <label>Contact Number:</label>
      <input type="text" name="contact_number" required>
    </div>

    <div class="form-group-half">
      <label>Gender:</label>
      <select name="gender" required>
        <option value="" hidden>Select</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        <option value="Other">Other</option>
      </select>
    </div>

    <div class="form-group-full">
      <label>Address:</label>
      <input type="text" name="address" required>
    </div>

    <div class="form-group-half">
      <label>Blood Quantity (in ml):</label>
      <input type="number" name="blood_quantity" required>
    </div>

    <div class="form-group-half">
      <label>Collection Date:</label>
      <input type="date" name="collection_date" required>
    </div>

    <div class="form-group-full">
      <label>Email Address:</label>
      <input type="email" name="email" required>
    </div>

    <div class="form-group">
      <label>Type of Donation:</label>
      <select name="donation_type" required>
        <option value="" hidden>Select</option>
        <option value="In House">In House</option>
        <option value="Walk-In/Voluntary">Walk-In/Voluntary</option>
        <option value="Replacement">Replacement</option>
        <option value="Patient-Directed">Patient-Directed</option>
      </select>
    </div>

    <div class="form-group">
      <label>Location of Donation:</label>
      <select name="donation_location" required>
        <option value="" hidden>Select</option>
        <option value="Red Cross Area">Red Cross</option>
        <option value="School">School</option>
      </select>
    </div>

  </form>
  </main>

  <?php if ($showAlert === "success"): ?>
    <script>
      Swal.fire({
        title: 'Success!',
        text: 'New donor added successfully!',
        icon: 'success',
        confirmButtonColor: '#b30000',
        background: '#ffeaea',
        color: '#8b0000',
        confirmButtonText: 'OK'
      });
    </script>
  <?php elseif ($showAlert === "duplicate" && $duplicateDonorId !== null): ?>
    <script>
      Swal.fire({
        title: 'Duplicate donor',
        html: <?php echo json_encode(
          'A donor with this email or the same contact number and birth date is already registered (Donor ID: ' . $duplicateDonorId . ').',
          JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ); ?>,
        icon: 'warning',
        confirmButtonColor: '#b30000',
        background: '#ffeaea',
        color: '#8b0000',
        confirmButtonText: 'OK'
      });
    </script>
  <?php elseif ($showAlert === "validation" && $validationErrors !== []): ?>
    <script>
      Swal.fire({
        title: 'Please check the form',
        html: <?php echo json_encode('<ul style="text-align:left;margin:0;padding-left:1.2em;">' . implode('', array_map(static function (string $e): string {
          return '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
        }, $validationErrors)) . '</ul>', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        icon: 'info',
        confirmButtonColor: '#b30000',
        background: '#ffeaea',
        color: '#8b0000',
        confirmButtonText: 'OK'
      });
    </script>
  <?php elseif ($showAlert === "error"): ?>
    <script>
      Swal.fire({
        title: 'Error!',
        text: 'Failed to add donor.',
        icon: 'error',
        confirmButtonColor: '#b30000',
        background: '#ffeaea',
        color: '#8b0000',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>

  <script>
    function clearForm() {
      Swal.fire({
        title: 'Are you sure?',
        text: 'This will clear all the form fields!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, clear it!',
        cancelButtonText: 'No, keep it',
        confirmButtonColor: '#b30000',
        background: '#ffeaea',
        color: '#8b0000'
      }).then((result) => {
        if (result.isConfirmed) {
          document.getElementById('form').reset();
        }
      });
    }
  </script>

</body>
</html>

<?php ob_end_flush(); ?>

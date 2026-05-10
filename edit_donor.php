<?php
ob_start();
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/bdms_audit_log_helpers.php';
require_once __DIR__ . "/db.php";

$showAlert = "";
$id = $_GET['id'] ?? null;

if (!$id) {
  die("Invalid donor ID.");
}

$sql = "SELECT * FROM donors WHERE id = '$id'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
  die("Donor not found.");
}

$row = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = $_POST["name"];
  $birthdate = $_POST["birthdate"];
  $address = $_POST["address"];
  $blood_type = $_POST["blood_type"];
  $civil_status = $_POST["civil_status"];
  $donation_history = $_POST["donation_history"];
  $classification = $_POST["classification"];
  $contact_number = $_POST["contact_number"];
  $gender = $_POST["gender"];
  $blood_quantity = $_POST["blood_quantity"];
  $collection_date = $_POST["collection_date"];
  $email = $_POST["email"];
  $donation_type = $_POST["donation_type"];
  $donation_location = $_POST["donation_location"];

  $birthdateObj = new DateTime($birthdate);
  $current_date = new DateTime();
  $age = $current_date->diff($birthdateObj)->y;

  $update_sql = "UPDATE donors SET
    name = '$name',
    birthdate = '".$birthdateObj->format('Y-m-d')."',
    address = '$address',
    blood_type = '$blood_type',
    civil_status = '$civil_status',
    donation_history = '$donation_history',
    classification = '$classification',
    contact_number = '$contact_number',
    gender = '$gender',
    blood_quantity = '$blood_quantity',
    collection_date = '$collection_date',
    email = '$email',
    age = '$age',
    donation_type = '$donation_type',
    donation_location = '$donation_location'
    WHERE id = '$id'";

  if ($conn->query($update_sql) === TRUE) {
    $donorIdInt = (int) $id;
    bdms_audit_log_insert($conn, 'update_donor', 'donor', $donorIdInt, [
      'donor_id' => $donorIdInt,
      'name' => $name,
      'blood_type' => $blood_type,
      'email' => $email,
      'classification' => $classification,
    ]);
    $showAlert = "success";
    $row = $_POST;
  } else {
    $showAlert = "error";
  }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Donor</title>
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
      font-family: Arial, sans-serif;
      background: #ffeaea;
      padding: 60px;
      font-size: 14px;
      overflow-x: hidden;
    }

    h2 {
      text-align: center;
      color: #8b0000;
      margin-bottom: 30px;
      font-size: 28px;
      font-family: 'Montserrat', sans-serif;
      font-weight: 500;
      text-transform: uppercase;
    }

    h2 i {
      margin-right: 10px;
    }

    form {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
    }

    .form-group,
    .form-group-half,
    .form-group-full {
      display: flex;
      flex-direction: column;
    }

    .form-group {
      flex: 1 1 calc(33.333% - 20px);
    }

    .form-group-half {
      flex: 1 1 calc(50% - 20px);
    }

    .form-group-full {
      flex: 1 1 100%;
    }

    label {
      font-weight: bold;
      margin-bottom: 5px;
      font-size: 13px;
    }

    input,
    select {
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 13px;
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

      form {
        gap: 15px;
      }

      .form-group,
      .form-group-half {
        flex: 1 1 calc(50% - 15px);
      }

      .button-wrapper {
        flex-direction: column;
        gap: 10px;
      }

      .button-wrapper button {
        width: 90%;
      }
    }

    @media (max-width: 500px) {
      .form-group,
      .form-group-half {
        flex: 1 1 100%;
      }
    }
  </style>
</head>
<body>

<h2><i class="fas fa-user-edit"></i> Edit Donor</h2>
<form id="form" method="POST" action="">

  <div class="form-group">
    <label>Full Name:</label>
    <input type="text" name="name" value="<?= $row['name'] ?>" required>
  </div>

  <div class="form-group">
    <label>Birth Date:</label>
    <input type="date" name="birthdate" value="<?= $row['birthdate'] ?>" required>
  </div>

  <div class="form-group">
    <label>Blood Type:</label>
    <select name="blood_type" required>
      <option value="" hidden>Select</option>
      <?php
      $types = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
      foreach ($types as $type) {
        echo "<option value='$type' ".($row['blood_type'] == $type ? 'selected' : '').">$type</option>";
      }
      ?>
    </select>
  </div>

  <div class="form-group">
    <label>Civil Status:</label>
    <select name="civil_status" required>
      <?php
      $statuses = ['Single','Married','Widowed'];
      foreach ($statuses as $status) {
        echo "<option value='$status' ".($row['civil_status'] == $status ? 'selected' : '').">$status</option>";
      }
      ?>
    </select>
  </div>

  <div class="form-group">
    <label>History of Blood Donation:</label>
    <select name="donation_history" required>
      <?php
      $histories = ['First Time','Regular Donor','Occasional Donor'];
      foreach ($histories as $hist) {
        echo "<option value='$hist' ".($row['donation_history'] == $hist ? 'selected' : '').">$hist</option>";
      }
      ?>
    </select>
  </div>

  <div class="form-group">
    <label>Classification:</label>
    <select name="classification" required>
      <?php
      $classifications = ['Student','Staff','Public'];
      foreach ($classifications as $class) {
        echo "<option value='$class' ".($row['classification'] == $class ? 'selected' : '').">$class</option>";
      }
      ?>
    </select>
  </div>

  <div class="form-group-half">
    <label>Contact Number:</label>
    <input type="text" name="contact_number" value="<?= $row['contact_number'] ?>" required>
  </div>

  <div class="form-group-half">
    <label>Gender:</label>
    <select name="gender" required>
      <?php
      $genders = ['Male','Female','Other'];
      foreach ($genders as $g) {
        echo "<option value='$g' ".($row['gender'] == $g ? 'selected' : '').">$g</option>";
      }
      ?>
    </select>
  </div>

  <div class="form-group-full">
    <label>Address:</label>
    <input type="text" name="address" value="<?= $row['address'] ?>" required>
  </div>

  <div class="form-group-half">
    <label>Blood Quantity (in ml):</label>
    <input type="number" name="blood_quantity" value="<?= $row['blood_quantity'] ?>" required>
  </div>

  <div class="form-group-half">
    <label>Collection Date:</label>
    <input type="date" name="collection_date" value="<?= $row['collection_date'] ?>" required>
  </div>

  <div class="form-group-full">
    <label>Email Address:</label>
    <input type="email" name="email" value="<?= $row['email'] ?>" required>
  </div>

  <div class="form-group">
    <label>Type of Donation:</label>
    <select name="donation_type" required>
      <?php
      $types = ['In House','Walk-In/Voluntary','Replacement','Patient-Directed'];
      foreach ($types as $type) {
        echo "<option value='$type' ".($row['donation_type'] == $type ? 'selected' : '').">$type</option>";
      }
      ?>
    </select>
  </div>

  <div class="form-group">
    <label>Location of Donation:</label>
    <select name="donation_location" required>
      <?php
      $locations = ['Red Cross Area','School'];
      foreach ($locations as $loc) {
        echo "<option value='$loc' ".($row['donation_location'] == $loc ? 'selected' : '').">$loc</option>";
      }
      ?>
    </select>
  </div>
</form>

<div class="button-wrapper">
  <button type="submit" form="form"><i class="fas fa-save"></i> Save Changes</button>
  <button type="button" id="clearBtn"><i class="fas fa-eraser"></i> Clear All</button>
  <button type="button" onclick="window.location.href='view_donor.php?id=<?= $id ?>';"><i class="fas fa-eye"></i> View Donor Info</button>
</div>

<?php if ($showAlert == "success"): ?>
<script>
Swal.fire({
  title: 'Updated!',
  text: 'Donor information updated successfully!',
  icon: 'success',
  confirmButtonColor: '#b30000',
  background: '#ffeaea',
  color: '#8b0000',
  confirmButtonText: 'OK'
});
</script>
<?php elseif ($showAlert == "error"): ?>
<script>
Swal.fire({
  title: 'Error!',
  text: 'Failed to update donor.',
  icon: 'error',
  confirmButtonColor: '#b30000',
  background: '#ffeaea',
  color: '#8b0000',
  confirmButtonText: 'OK'
});
</script>
<?php endif; ?>

<script>
document.getElementById('clearBtn').addEventListener('click', function () {
  Swal.fire({
    title: 'Are you sure?',
    text: 'This will clear all the form fields.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#b30000',
    cancelButtonColor: '#999',
    confirmButtonText: 'Yes, clear it!',
    cancelButtonText: 'Cancel',
    background: '#ffeaea',
    color: '#8b0000'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.getElementById('form');
      form.querySelectorAll('input, select, textarea').forEach(el => {
        if (el.type !== 'submit' && el.type !== 'button') {
          el.value = '';
        }
      });

      Swal.fire({
        title: 'Cleared!',
        text: 'The form has been reset.',
        icon: 'success',
        confirmButtonColor: '#b30000',
        background: '#ffeaea',
        color: '#8b0000'
      });
    }
  });
});
</script>

</body>
</html>

<?php ob_end_flush(); ?>

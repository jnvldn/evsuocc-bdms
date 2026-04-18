<?php
ob_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bdms";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$showAlert = "";

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

  $sql = "INSERT INTO donors (name, birthdate, address, blood_type, civil_status, donation_history, classification, contact_number, gender, blood_quantity, collection_date, email, age, donation_type, donation_location)
          VALUES ('$name', '".$birthdateObj->format('Y-m-d')."', '$address', '$blood_type', '$civil_status', '$donation_history', '$classification', '$contact_number', '$gender', '$blood_quantity', '$collection_date', '$email', '$age', '$donation_type', '$donation_location')";

  if ($conn->query($sql) === TRUE) {
    $showAlert = "success";
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
  <title>Add Donor</title>
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
      overflow: hidden;
    }

    h2 {
      text-align: center;
      color: #8b0000;
      margin-bottom: 30px;
      margin-top: -20px;
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
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
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

      table {
        font-size: 12px;
      }

      .button-wrapper {
        flex-direction: column;
        gap: 10px;
      }
    }
  </style>
</head>
<body>

  <h2><i class="fas fa-user-plus"></i>Add New Donor</h2>

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

    <div class="button-wrapper">
      <button type="submit"><i class="fas fa-plus"></i> Add Donor</button>
      <button type="button" onclick="clearForm();"><i class="fas fa-trash-alt"></i> Clear All</button>
      <button type="button" onclick="window.location.href='dashboard.php';"><i class="fas fa-tachometer-alt"></i> Dashboard</button>
      <button type="button" onclick="window.location.href='donors_list.php';"><i class="fas fa-list"></i> Donors List</button>
    </div>

  </form>

  <?php if ($showAlert == "success"): ?>
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
  <?php elseif ($showAlert == "error"): ?>
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

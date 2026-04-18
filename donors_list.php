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

$sql = "SELECT * FROM donors";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<head>
  <meta charset="UTF-8">
  <title>Donors List</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: #b30000;
      font-size: 32px;
      font-weight: 500;
      margin-bottom: 40px;
      letter-spacing: 1px;
    }
    .search-container {
      display: flex;
      align-items: center;
      background-color: #fff;
      border-radius: 30px;
      padding: 5px 15px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .search-container input {
      border: none;
      outline: none;
      padding: 8px 12px;
      border-radius: 25px;
      font-size: 14px;
      margin-right: 10px;
    }
    .search-container button {
      background-color: #b30000;
      color: white;
      padding: 8px 12px;
      border: none;
      border-radius: 50%;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .search-container button:hover {
      background-color: #8b0000;
    }
    .table-container {
      width: 100%;
      height: 460px;
      overflow-y: auto;
      margin-top: 20px;
      border: 1px solid #ddd;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
      border-radius: 8px;
    }
    thead th {
      background-color: #f4f4f4;
      color: #b30000;
      position: sticky;
      top: 0;
      z-index: 2;
      padding: 12px;
      text-align: center;
      font-size: 14px;
      border-right: 1px solid #e0e0e0;
    }
    tbody td {
      padding: 12px;
      text-align: center;
      font-size: 14px;
      border-top: 1px solid #f1d0d0;
      border-right: 1px solid #e0e0e0;
    }
    td:last-child, th:last-child {
      border-right: none;
    }
    td a {
      text-decoration: none;
      color: #b30000;
      font-weight: bold;
      display: inline-block;
      padding: 8px 16px;
      border-radius: 5px;
      transition: all 0.3s ease;
    }
    td a:hover {
      color: #b30000;
      border-bottom: 3px solid #b30000;
      padding-bottom: 5px;
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

<h2>
  <span><i class="fas fa-users" style="margin-right: 10px;"></i>List of Donors</span>
  <div class="search-container">
    <input type="text" placeholder="Search..." id="searchInput" oninput="searchDonor()">
    <button><i class="fas fa-search"></i></button>
  </div>
</h2>

<?php if ($result->num_rows > 0): ?>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Blood Type</th>
          <th>Contact Number</th>
          <th>Email</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="donorTable">
        <?php while($row = $result->fetch_assoc()): ?>
          <tr id="donor-<?php echo $row['id']; ?>">
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['name']; ?></td>
            <td><?php echo $row['blood_type']; ?></td>
            <td><?php echo $row['contact_number']; ?></td>
            <td><?php echo $row['email']; ?></td>
            <td>
              <a href="view_donor.php?id=<?php echo $row['id']; ?>"><i class="fas fa-eye"></i> View</a> | 
              <a href="edit_donor.php?id=<?php echo $row['id']; ?>"><i class="fas fa-edit"></i> Edit</a> | 
              <a href="javascript:void(0);" onclick="deleteDonor(<?php echo $row['id']; ?>)"><i class="fas fa-trash-alt"></i> Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <p>No donors found.</p>
<?php endif; ?>

<div class="button-wrapper">
  <button onclick="window.location.href='dashboard.php';"><i class="fas fa-tachometer-alt"></i> Dashboard</button>
  <button onclick="window.location.href='add.php';"><i class="fas fa-plus"></i> Add New Donor</button>
</div>

<script>
function searchDonor() {
  var input = document.getElementById("searchInput").value.toLowerCase();
  var table = document.getElementById("donorTable");
  var rows = table.getElementsByTagName("tr");
  var found = false;

  if (input === "") {
    for (var i = 0; i < rows.length; i++) {
      rows[i].style.display = "";
    }
    return;
  }

  for (var i = 0; i < rows.length; i++) {
    var cells = rows[i].getElementsByTagName("td");
    var match = false;

    for (var j = 0; j < cells.length - 1; j++) {
      if (cells[j] && cells[j].innerText.toLowerCase().includes(input)) {
        match = true;
      }
    }

    rows[i].style.display = match ? "" : "none";
    if (match) found = true;
  }

  if (!found) {
    Swal.fire({
      icon: 'error',
      title: 'No results found!',
      text: 'We couldn\'t find any donors matching your search.',
      confirmButtonColor: '#b30000',
      confirmButtonText: '<i class="fas fa-check-circle"></i> OK'
    });
  }
}

function deleteDonor(id) {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'No, cancel!',
    confirmButtonColor: '#b30000',
    cancelButtonColor: '#d33'
  }).then((result) => {
    if (result.isConfirmed) {
      fetch(`delete_donor.php?id=${id}`)
        .then(response => response.text())
        .then(data => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'The donor has been deleted.',
            confirmButtonColor: '#b30000',
            confirmButtonText: '<i class="fas fa-check-circle"></i> OK'
          }).then(() => {
            document.getElementById(`donor-${id}`).remove();
          });
        })
        .catch(error => {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Something went wrong while deleting the donor.',
            confirmButtonColor: '#b30000',
            confirmButtonText: '<i class="fas fa-check-circle"></i> OK'
          });
        });
    }
  });
}
</script>

<?php
$conn->close();
ob_end_flush();
?>
</body>
</html>

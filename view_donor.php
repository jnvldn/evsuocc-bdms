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

if (isset($_GET['id'])) {
    $donor_id = $_GET['id'];

    $sql = "SELECT * FROM donors WHERE id = $donor_id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $donor = $result->fetch_assoc();
    } else {
        echo "No donor found with this ID.";
        exit;
    }
} else {
    echo "No donor ID provided.";
    exit;
}

$conn->close();
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Donor</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: #fef2f2;
            margin: 0;
            padding: 30px;
            font-size: 14px;
            color: #4b4b4b;
        }

        h2 {
            text-align: center;
            color: #b30000;
            margin-bottom: 20px;
            font-size: 28px;
            font-weight: 500;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            margin: 0 auto;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-radius: 2px;
            overflow: hidden;
        }

        th, td {
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background-color: white;
            color: #b30000;
            font-weight: bold;
        }

        td:first-child {
            border-left: 2px solid #b30000;
            border-top: 2px solid #b30000;
            border-bottom: 2px solid #b30000;
            background-color: #ffffff;
            color: #b30000;
            font-weight: bold;
        }

        tr {
            border-bottom: 2px solid #b30000;
        }

        td:nth-child(2) {
            border-left: 2px solid #000000;
            border-right: 2px solid #000000;
            background-color: #ffffff;
            color: black;
        }

        td i {
            color: #b30000;
            margin-right: 8px;
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

    <h2>Donor Details</h2>

    <table>
        <tr>
            <th><i class="fas fa-user"></i> Full Name</th>
            <td><?php echo $donor['name']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-calendar-alt"></i> Birth Date</th>
            <td><?php echo $donor['birthdate']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-tint"></i> Blood Type</th>
            <td><?php echo $donor['blood_type']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-heart"></i> Civil Status</th>
            <td><?php echo $donor['civil_status']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-history"></i> Donation History</th>
            <td><?php echo $donor['donation_history']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-tag"></i> Classification</th>
            <td><?php echo $donor['classification']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-phone"></i> Contact Number</th>
            <td><?php echo $donor['contact_number']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-genderless"></i> Gender</th>
            <td><?php echo $donor['gender']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-map-marker-alt"></i> Address</th>
            <td><?php echo $donor['address']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-tint"></i> Blood Quantity (ml)</th>
            <td><?php echo $donor['blood_quantity']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-calendar-day"></i> Collection Date</th>
            <td><?php echo $donor['collection_date']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-envelope"></i> Email Address</th>
            <td><?php echo $donor['email']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-donate"></i> Type of Donation</th>
            <td><?php echo $donor['donation_type']; ?></td>
        </tr>
        <tr>
            <th><i class="fas fa-location-arrow"></i> Location of Donation</th>
            <td><?php echo $donor['donation_location']; ?></td>
        </tr>
    </table>

    <div class="button-wrapper">
        <button onclick="window.location.href='donors_list.php';"><i class="fas fa-list"></i>Donors List</button>
    </div>

</body>
</html>

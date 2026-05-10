<?php  
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . "/db.php";

$selected_month = isset($_POST['month']) ? $_POST['month'] : 'all';

$sql = "SELECT
            name,
            contact_number,
            email,
            age,
            gender,
            blood_type,
            collection_date,
            civil_status AS status,
            COUNT(*) AS donation_count,
            CASE
                WHEN COUNT(*) = 1 THEN 'Once'
                WHEN COUNT(*) > 1 THEN 'Multiple Times'
                ELSE 'Not Available'
            END AS donation_frequency
        FROM donors";

if ($selected_month !== 'all') {
    $sql .= " WHERE MONTH(collection_date) = '$selected_month'";
}

$sql .= " GROUP BY
            name,
            contact_number,
            email,
            age,
            gender,
            blood_type,
            civil_status,
            collection_date";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Donor Report</title>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            color: #b30000;
            font-size: 32px;
            font-weight: 500;
            margin-bottom: 40px;
            letter-spacing: 1px;
            text-align: left;
        }
        #datetime {
            font-size: 16px;
            color: #b30000;
            margin-top: 10px;
            text-align: left;
            font-weight: 300;
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .month-filter {
            text-align: center;
            position: relative;
        }
        .month-filter select {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
            appearance: none;
            background-color: #fff;
            padding-right: 30px;
        }
        .month-filter::after {
            content: '\f0d7';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #b30000;
        }
        .month-filter label {
            margin-right: 10px;
            font-weight: bold;
            font-size: 16px;
            color: #b30000;
        }
        .table-container {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            background-color: #f4f4f4;
            color: #b30000;
            padding: 14px;
            text-align: center;
            font-size: 14px;
            border-right: 1px solid #e0e0e0;
        }
        td {
            padding: 12px;
            text-align: center;
            font-size: 14px;
            border-top: 1px solid #f1d0d0;
            border-right: 1px solid #e0e0e0;
        }
        .contact-list {
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding-left: 8px;
        }
        .contact-list i {
            margin-right: 8px;
            color:rgb(179, 81, 81);
        }
        .name-cell {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: flex-start;
            text-align: left;
            padding: 12px;
        }
        .name-cell i {
            font-size: 16px;
        }
        .female-icon {
            color: #e91e63;
        }
        .male-icon {
            color: #2196f3;
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
        }
        .button-wrapper button:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(179, 0, 0, 0.6);
        }
        .signature-print {
            display: none;
            margin-top: 40px;
            font-weight: bold;
            font-size: 16px;
            text-align: right;
            color: #000;
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }
            .button-wrapper {
                display: none !important;
            }
            .signature-print {
                display: block;
                color: red;
            }
            table th:nth-child(10), table td:nth-child(10) {
                display: table-cell !important;
            }
            @page {
                size: portrait;
                margin: 10mm;
            }
            table {
                width: 100% !important;
                page-break-inside: auto;
            }
            table th, table td {
                font-size: 12px;
                padding: 8px;
            }
            table td:nth-child(2), table th:nth-child(2) {
                width: 250px;
                text-align: left;
                padding-left: 10px;
                word-wrap: break-word;
                white-space: normal;
            }
        }

        table td:nth-child(1), table th:nth-child(1) {
            width: 250px;
        }
        table td:nth-child(2), table th:nth-child(2) {
            width: 350px;
        }
    </style>
</head>
<body>

<p style="background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px;">
  <strong>New:</strong> Use <a href="reports.php" style="color:#b30000;font-weight:600;">Generate Report</a> for donor and inventory reports with date range, filters, and CSV/PDF export.
</p>

<div class="header-container">
    <h2><i class="fas fa-tint"></i> Donor Report</h2>
    <div id="datetime"></div>
    <div class="month-filter">
        <form method="POST">
            <label for="month"><i class="fas fa-calendar-alt"></i> Select Month:</label>
            <select name="month" id="month" onchange="this.form.submit()">
                <option value="all" <?php if ($selected_month == 'all') echo 'selected'; ?>>Select All</option>
                <?php
                for ($m = 1; $m <= 12; $m++) {
                    $selected = ($selected_month == $m) ? 'selected' : '';
                    $monthName = date('F', mktime(0, 0, 0, $m, 10));
                    echo "<option value='$m' $selected>$monthName</option>";
                }
                ?>
            </select>
        </form>
    </div>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Blood Type</th>
                <th>Donation Date(s)</th>
                <th>Number of Donations</th>
                <th>Status</th>
                <th>Medical Eligibility</th>
                <th>Donation Frequency</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $phone = !empty($row["contact_number"]) ? $row["contact_number"] : "Not Provided";
                    $email = !empty($row["email"]) ? $row["email"] : "Not Provided";
                    $iconColor = $row["gender"] === "Female" ? "female-icon" : "male-icon";
                    $formatted_date = date("m-d-Y", strtotime($row["collection_date"]));

                    echo "<tr>";
                    echo "<td class='name-cell'><i class='fas fa-user $iconColor'></i><span>" . htmlspecialchars($row["name"]) . "</span></td>";
                    echo "<td><div class='contact-list'>
                            <span><i class='fas fa-phone-alt'></i>" . htmlspecialchars($phone) . "</span>
                            <span><i class='fas fa-envelope'></i>" . htmlspecialchars($email) . "</span>
                          </div></td>";
                    echo "<td>" . htmlspecialchars($row["age"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["gender"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["blood_type"]) . "</td>";
                    echo "<td>" . htmlspecialchars($formatted_date) . "</td>";
                    echo "<td>" . htmlspecialchars($row["donation_count"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["status"]) . "</td>";
                    echo "<td>" . ($row["donation_count"] > 0 ? "Eligible" : "Not Yet Eligible") . "</td>";
                    echo "<td>" . htmlspecialchars($row["donation_frequency"]) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='10'>No donor data available.</td></tr>";
            }
            $conn->close();
            ?>
        </tbody>
    </table>
</div>

<div class="signature-print">Organizer: Mr. Bernie Palacio</div>

<div class="button-wrapper">
    <button onclick="window.location.href='dashboard.php';"><i class="fas fa-tachometer-alt"></i> Dashboard</button>
    <button onclick="window.print();"><i class="fas fa-print"></i> Print Report</button>
</div>

<script>
    function updateTime() {
        const now = new Date();
        const options = {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        };
        document.getElementById('datetime').textContent = now.toLocaleDateString('en-US', options);
    }
    setInterval(updateTime, 1000);
    updateTime();
</script>

</body>
</html>

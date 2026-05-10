<?php
session_start();
$title = 'BDMS Sign in';

if (isset($_SESSION['user'])) {
    $to_audit = (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin')
        || (string) ($_SESSION['user'] ?? '') === 'superadmin';
    header($to_audit ? 'Location: audit_log.php' : 'Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        display: flex;
        align-items: center;
        background: url('kkk.png') no-repeat center center fixed;
        background-size: cover;
        font-family: 'Poppins', sans-serif;
        color: white;
        font-size: 25px;
    }

    .container {
        position: fixed;
        top: 50%;
        left: 15%;
        transform: translateY(-50%);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10;
        width: 100%;
        height: 100%;
    }

    .form {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        transition: all 1s ease;
    }

    .form .form_front {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 20px;
        position: relative;
        width: 600px;
        height: 600px;
        padding: 30px 30px;
        border-radius: 15px;
        background: #d33939;
        border: 1px solid #ffcccc;
        box-sizing: border-box;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        outline: 3px solid rgba(255, 215, 0, 0.55);
        outline-offset: 5px;
    }

    .login-role-hint {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.92);
        text-align: center;
        line-height: 1.45;
        max-width: 320px;
        margin: 0;
        padding: 10px 14px;
        background: rgba(0, 0, 0, 0.12);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.22);
    }

    .login-role-hint strong {
        color: #fff;
    }

    .error-msg {
        color: black;
        background-color: #ffe0e0;
        border-left: 5px solid #b00020;
        padding: 0.5rem;
        border-radius: 5px;
        font-size: 15px;
        text-align: center;
        min-height: 20px;
        margin: 0;
    }

    .form_details {
        font-size: 28px;
        font-weight: 600;
        padding-bottom: 10px;
        color: white;
    }

    .input {
        width: 245px;
        min-height: 45px;
        color: #b00020;
        outline: none;
        transition: 0.35s;
        padding: 0px 7px;
        background-color: #fff0f0;
        border-radius: 6px;
        border: 1px solid #ffccd5;
        box-shadow: none;
        font-size: 16px;
    }

    .input::placeholder {
        color: #b00020;
    }

    .input:focus {
        transform: scale(1.05);
        box-shadow: none;
        border-color: #b00020;
        background-color: #ffe6e6;
    }

    .btn {
        width: 257px;
        min-height: 45px;
        padding: 0px 7px;
        cursor: pointer;
        background-color: #b00020;
        border-radius: 6px;
        border: none;
        box-shadow: none;
        color: white;
        font-size: 16px;
        font-weight: bold;
        transition: 0.35s;
    }

    .btn:hover {
        transform: scale(1.05);
        background-color: #a0001c;
    }
    </style>
</head>
<body>
    <div class="container">
        <form id="loginForm" class="form" action="authenticate.php" method="POST" autocomplete="off">
            <div class="form_front">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img src="evsulogo.png" alt="EVSU Logo" style="width: 50px; height: 50px;">
                    <h2 style="margin: 0; margin-bottom: 10px;">EVSU-OCC BDMS</h2>
                </div>

                <div class="form_details" style="margin-top: 28px;">Sign in</div>
                <p class="login-role-hint">After login, the dashboard shows your account in a <strong>profile ring</strong>: gold border for <strong>Administrator</strong>, blue for <strong>Staff</strong>. Staff can register and manage donors, but <strong>only administrators can delete</strong> donor records or open User Management.</p>

                <?php
                if (isset($_SESSION['error'])) {
                    echo "<p class='error-msg'>" . $_SESSION['error'] . "</p>";
                    unset($_SESSION['error']);
                }
                ?>

                <input name="username" class="input" type="text" placeholder="Username" required autocomplete="off">
                <input name="password" class="input" type="password" placeholder="Password" required autocomplete="new-password">
                <button id="loginBtn" class="btn" type="submit">Login</button>
            </div>
        </form>
    </div>

    <script>
    (function () {
        const form = document.getElementById('loginForm');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            // If SweetAlert2 didn't load (CDN blocked/offline), fall back to normal submit.
            if (typeof Swal === 'undefined' || !Swal || typeof Swal.fire !== 'function') {
                return;
            }

            e.preventDefault();
            Swal.fire({
                title: 'Proceed to login?',
                text: "Please confirm your login attempt.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#b00020',
                cancelButtonColor: '#999',
                confirmButtonText: 'Yes, login',
                background: '#fff0f0',
                color: '#b00020'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    })();
    </script>
</body>
</html>

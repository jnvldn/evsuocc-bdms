<?php
/**
 * US-06: Administrator-only staff account management (create, update, deactivate, activate, permanent delete).
 */
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/require_admin.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/staff_accounts_helpers.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$tableOk = false;
$chk = $conn->query("SHOW TABLES LIKE 'staff_users'");
if ($chk && $chk->num_rows > 0) {
    $tableOk = true;
}

$flash = '';
$flashType = '';
if (!empty($_SESSION['staff_mgmt_success'])) {
    $flash = (string) $_SESSION['staff_mgmt_success'];
    $flashType = 'success';
    unset($_SESSION['staff_mgmt_success']);
}

$editRow = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if ($tableOk && $editId > 0) {
    $es = $conn->prepare('SELECT id, username, email, display_name, role, is_active FROM staff_users WHERE id = ? LIMIT 1');
    $es->bind_param('i', $editId);
    $es->execute();
    $er = $es->get_result()->fetch_assoc();
    $es->close();
    if ($er) {
        $editRow = $er;
    }
}

$currentStaffId = isset($_SESSION['staff_id']) ? (int) $_SESSION['staff_id'] : 0;

if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $check = staff_validate_create_payload($_POST);
        if (!$check['ok']) {
            $flashType = 'validation';
            $_SESSION['staff_flash_errors'] = $check['errors'];
        } else {
            $username = trim((string) $_POST['username']);
            $email = staff_normalize_email((string) $_POST['email']);
            $displayName = trim((string) $_POST['display_name']);
            $role = (string) $_POST['role'];
            $hash = password_hash((string) $_POST['password'], PASSWORD_DEFAULT);
            $ins = $conn->prepare(
                'INSERT INTO staff_users (username, email, display_name, password_hash, role, is_active)
                 VALUES (?, ?, ?, ?, ?, 1)'
            );
            try {
                $ins->bind_param('sssss', $username, $email, $displayName, $hash, $role);
                $ins->execute();
                $ins->close();
                $_SESSION['staff_mgmt_success'] = 'Staff account created successfully.';
                header('Location: user_management.php');
                exit;
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $flash = 'Username or email is already in use.';
                } else {
                    $flash = 'Could not create account. Please try again.';
                }
                $flashType = 'error';
            }
        }
    } elseif ($action === 'update' && $editId > 0) {
        $check = staff_validate_update_payload($_POST);
        if (!$check['ok']) {
            $flashType = 'validation';
            $_SESSION['staff_flash_errors'] = $check['errors'];
        } else {
            $email = staff_normalize_email((string) $_POST['email']);
            $displayName = trim((string) $_POST['display_name']);
            $role = (string) $_POST['role'];
            $newPass = (string) ($_POST['password'] ?? '');
            $cur = $conn->prepare('SELECT role, is_active FROM staff_users WHERE id = ? LIMIT 1');
            $cur->bind_param('i', $editId);
            $cur->execute();
            $curRow = $cur->get_result()->fetch_assoc();
            $cur->close();
            if (!$curRow) {
                $flash = 'Account not found.';
                $flashType = 'error';
            } elseif (
                $curRow['role'] === 'administrator'
                && $role === 'staff'
                && staff_count_administrator_rows($conn) <= 1
            ) {
                $flash = 'Cannot remove the only administrator account from the system.';
                $flashType = 'error';
            } else {
                try {
                    if ($newPass !== '') {
                        $hash = password_hash($newPass, PASSWORD_DEFAULT);
                        $upd = $conn->prepare(
                            'UPDATE staff_users SET email = ?, display_name = ?, role = ?, password_hash = ?
                             WHERE id = ?'
                        );
                        $upd->bind_param('ssssi', $email, $displayName, $role, $hash, $editId);
                    } else {
                        $upd = $conn->prepare(
                            'UPDATE staff_users SET email = ?, display_name = ?, role = ?
                             WHERE id = ?'
                        );
                        $upd->bind_param('sssi', $email, $displayName, $role, $editId);
                    }
                    $upd->execute();
                    $upd->close();
                    $_SESSION['staff_mgmt_success'] = 'Account updated successfully.';
                    header('Location: user_management.php');
                    exit;
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() === 1062) {
                        $flash = 'That email is already used by another account.';
                    } else {
                        $flash = 'Could not update account.';
                    }
                    $flashType = 'error';
                }
            }
        }
    } elseif ($action === 'deactivate') {
        $targetId = (int) ($_POST['staff_id'] ?? 0);
        if ($targetId <= 0) {
            $flash = 'Invalid account.';
            $flashType = 'error';
        } elseif ($currentStaffId > 0 && $targetId === $currentStaffId) {
            $flash = 'You cannot deactivate your own account while logged in.';
            $flashType = 'error';
        } else {
            $roleStmt = $conn->prepare('SELECT role FROM staff_users WHERE id = ? AND is_active = 1 LIMIT 1');
            $roleStmt->bind_param('i', $targetId);
            $roleStmt->execute();
            $rr = $roleStmt->get_result()->fetch_assoc();
            $roleStmt->close();
            if (!$rr) {
                $flash = 'Account not found or already inactive.';
                $flashType = 'error';
            } elseif ($rr['role'] === 'administrator' && staff_count_active_admins($conn) <= 1) {
                $flash = 'Cannot deactivate the last active administrator.';
                $flashType = 'error';
            } else {
                $de = $conn->prepare('UPDATE staff_users SET is_active = 0 WHERE id = ?');
                $de->bind_param('i', $targetId);
                $de->execute();
                $de->close();
                $_SESSION['staff_mgmt_success'] = 'Staff account deactivated. That user can no longer sign in.';
                header('Location: user_management.php');
                exit;
            }
        }
    } elseif ($action === 'activate') {
        $targetId = (int) ($_POST['staff_id'] ?? 0);
        if ($targetId <= 0) {
            $flash = 'Invalid account.';
            $flashType = 'error';
        } else {
            $chk = $conn->prepare('SELECT id, username FROM staff_users WHERE id = ? AND is_active = 0 LIMIT 1');
            $chk->bind_param('i', $targetId);
            $chk->execute();
            $inactive = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$inactive) {
                $flash = 'Account not found or already active.';
                $flashType = 'error';
            } else {
                try {
                    $act = $conn->prepare('UPDATE staff_users SET is_active = 1 WHERE id = ? AND is_active = 0');
                    $act->bind_param('i', $targetId);
                    $act->execute();
                    $act->close();
                    $_SESSION['staff_mgmt_success'] = 'Account activated. The user can sign in again.';
                    header('Location: user_management.php');
                    exit;
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() === 1062) {
                        $flash = 'Cannot activate: username or email conflicts with another account. Edit this account first.';
                    } else {
                        $flash = 'Could not activate account.';
                    }
                    $flashType = 'error';
                }
            }
        }
    } elseif ($action === 'permanent_delete') {
        $targetId = (int) ($_POST['staff_id'] ?? 0);
        if ($targetId <= 0) {
            $flash = 'Invalid account.';
            $flashType = 'error';
        } elseif ($currentStaffId > 0 && $targetId === $currentStaffId) {
            $flash = 'You cannot delete your own account while logged in.';
            $flashType = 'error';
        } else {
            $sel = $conn->prepare('SELECT id, role, is_active FROM staff_users WHERE id = ? LIMIT 1');
            $sel->bind_param('i', $targetId);
            $sel->execute();
            $delRow = $sel->get_result()->fetch_assoc();
            $sel->close();
            if (!$delRow) {
                $flash = 'Account not found.';
                $flashType = 'error';
            } elseif ((int) $delRow['is_active'] === 1) {
                $flash = 'Deactivate the account first, then you can delete it permanently.';
                $flashType = 'error';
            } elseif ($delRow['role'] === 'administrator' && staff_count_administrator_rows($conn) <= 1) {
                $flash = 'Cannot delete the only administrator record in the system.';
                $flashType = 'error';
            } else {
                $del = $conn->prepare('DELETE FROM staff_users WHERE id = ? AND is_active = 0');
                $del->bind_param('i', $targetId);
                $del->execute();
                if ($del->affected_rows === 0) {
                    $flash = 'No row was deleted (account may have been removed or reactivated).';
                    $flashType = 'error';
                } else {
                    $_SESSION['staff_mgmt_success'] = 'Account permanently removed from the database.';
                    header('Location: user_management.php');
                    exit;
                }
                $del->close();
            }
        }
    }
}

$validationErrors = [];
if (!empty($_SESSION['staff_flash_errors'])) {
    $validationErrors = $_SESSION['staff_flash_errors'];
    unset($_SESSION['staff_flash_errors']);
}

$staffList = [];
if ($tableOk) {
    $list = $conn->query(
        'SELECT id, username, email, display_name, role, is_active, created_at
         FROM staff_users ORDER BY is_active DESC, display_name ASC'
    );
    if ($list) {
        while ($row = $list->fetch_assoc()) {
            $staffList[] = $row;
        }
    }
}

$conn->close();
$pageTitle = $editRow ? 'Edit staff account' : 'User management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> — BDMS</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body { font-family: 'Poppins', sans-serif; margin: 0; background: #f4f4f4; color: #333; padding: 24px; }
    h1 { color: #b30000; font-weight: 600; font-size: 1.5rem; margin: 0 0 8px; }
    .sub { color: #666; margin-bottom: 24px; font-size: 0.95rem; }
    .card {
      background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      padding: 24px; margin-bottom: 24px; max-width: 960px;
    }
    label { display: block; font-weight: 500; margin-bottom: 6px; font-size: 0.9rem; }
    input[type="text"], input[type="email"], input[type="password"], select {
      width: 100%; max-width: 420px; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px;
      font-family: inherit; font-size: 0.95rem; box-sizing: border-box;
    }
    .row { margin-bottom: 16px; }
    .btn {
      display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border: none; border-radius: 8px;
      cursor: pointer; font-weight: 600; font-size: 0.9rem; text-decoration: none;
    }
    .btn-primary { background: #b30000; color: #fff; }
    .btn-primary:hover { background: #8b0000; }
    .btn-secondary { background: #e0e0e0; color: #333; }
    .btn-danger { background: #c62828; color: #fff; }
    .btn-danger:hover { background: #a31818; }
    .btn-success { background: #2e7d32; color: #fff; }
    .btn-success:hover { background: #1b5e20; }
    .btn-purge { background: #4a148c; color: #fff; }
    .btn-purge:hover { background: #311b92; }
    .top-nav { margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #eee; }
    th { background: #fafafa; color: #b30000; font-weight: 600; }
    .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .badge-admin { background: #ffe0e0; color: #8b0000; }
    .badge-staff { background: #e3f2fd; color: #1565c0; }
    .badge-off { background: #eee; color: #777; }
    .actions { white-space: nowrap; }
    .actions a, .actions button { margin-right: 8px; font-size: 0.85rem; }
    .muted { color: #888; font-size: 0.85rem; }
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; max-width: 880px; }
    @media (max-width: 700px) { .grid2 { grid-template-columns: 1fr; } }
    .warn-box { background: #fff8e1; border-left: 4px solid #f9a825; padding: 12px 16px; margin-bottom: 20px; border-radius: 6px; }
  </style>
</head>
<body>

  <div class="top-nav">
    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
  </div>

  <h1><i class="fas fa-users-cog"></i> <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
  <p class="sub">Create and update staff accounts. <strong>Deactivate</strong> blocks sign-in; <strong>Activate</strong> restores access. <strong>Delete permanently</strong> removes an <em>inactive</em> row from the database (deactivate first).</p>

  <?php if (!$tableOk): ?>
    <div class="card warn-box">
      <strong>Database setup required.</strong> Run <code>schema_us06.sql</code> on your <code>bdms</code> database, then reload this page.
    </div>
  <?php else: ?>

    <?php if ($editRow): ?>
      <div class="card">
        <h2 style="margin-top:0;color:#b30000;font-size:1.1rem;">Edit account</h2>
        <?php if ((int) $editRow['is_active'] === 0): ?>
        <div class="warn-box" style="margin-bottom:16px;">
          This account is <strong>inactive</strong> (cannot sign in). Update details if needed, then use <strong>Activate</strong> from the list, or return to the list to activate or delete permanently.
        </div>
        <?php endif; ?>
        <p class="muted">Username: <strong><?php echo htmlspecialchars((string) $editRow['username'], ENT_QUOTES, 'UTF-8'); ?></strong> (cannot be changed)</p>
        <form method="post" action="user_management.php?edit=<?php echo (int) $editRow['id']; ?>">
          <input type="hidden" name="action" value="update">
          <div class="grid2">
            <div class="row">
              <label for="display_name">Full name</label>
              <input type="text" id="display_name" name="display_name" required maxlength="255"
                value="<?php echo htmlspecialchars((string) $editRow['display_name'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="row">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" required maxlength="255"
                value="<?php echo htmlspecialchars((string) $editRow['email'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="row">
              <label for="role">Role</label>
              <select id="role" name="role" required>
                <option value="staff" <?php echo $editRow['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                <option value="administrator" <?php echo $editRow['role'] === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
              </select>
            </div>
            <div class="row">
              <label for="password">New password (optional)</label>
              <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" placeholder="Leave blank to keep current">
            </div>
          </div>
          <div class="row">
            <label for="password_confirm">Confirm new password</label>
            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="8">
          </div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save changes</button>
          <a href="user_management.php" class="btn btn-secondary">Cancel</a>
        </form>
      </div>
    <?php else: ?>

      <div class="card">
        <h2 style="margin-top:0;color:#b30000;font-size:1.1rem;"><i class="fas fa-user-plus"></i> Add new staff</h2>
        <form method="post" action="user_management.php">
          <input type="hidden" name="action" value="create">
          <div class="grid2">
            <div class="row">
              <label for="username">Username (for login)</label>
              <input type="text" id="username" name="username" required maxlength="64" pattern="[a-zA-Z0-9_]{3,64}" title="3–64 letters, numbers, or underscore">
            </div>
            <div class="row">
              <label for="display_name">Full name</label>
              <input type="text" id="display_name" name="display_name" required maxlength="255">
            </div>
            <div class="row">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" required maxlength="255">
            </div>
            <div class="row">
              <label for="role">Role</label>
              <select id="role" name="role" required>
                <option value="staff" selected>Staff</option>
                <option value="administrator">Administrator</option>
              </select>
            </div>
            <div class="row">
              <label for="password">Password</label>
              <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="row">
              <label for="password_confirm">Confirm password</label>
              <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
            </div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create account</button>
        </form>
      </div>

      <div class="card">
        <h2 style="margin-top:0;color:#b30000;font-size:1.1rem;"><i class="fas fa-list"></i> Staff accounts</h2>
        <?php if (count($staffList) === 0): ?>
          <p class="muted">No accounts yet.</p>
        <?php else: ?>
          <div style="overflow-x:auto;">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th class="actions">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($staffList as $s): ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string) $s['display_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $s['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $s['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <?php if ($s['role'] === 'administrator'): ?>
                        <span class="badge badge-admin">Administrator</span>
                      <?php else: ?>
                        <span class="badge badge-staff">Staff</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ((int) $s['is_active'] === 1): ?>
                        <span class="badge" style="background:#e8f5e9;color:#2e7d32;">Active</span>
                      <?php else: ?>
                        <span class="badge badge-off">Inactive</span>
                      <?php endif; ?>
                    </td>
                    <td class="actions">
                      <a href="user_management.php?edit=<?php echo (int) $s['id']; ?>" class="btn btn-secondary" style="padding:6px 12px;font-size:0.8rem;"><i class="fas fa-edit"></i> Edit</a>
                      <?php if ((int) $s['is_active'] === 1): ?>
                        <form method="post" action="user_management.php" style="display:inline;" class="deactivate-form" data-name="<?php echo htmlspecialchars((string) $s['display_name'], ENT_QUOTES, 'UTF-8'); ?>">
                          <input type="hidden" name="action" value="deactivate">
                          <input type="hidden" name="staff_id" value="<?php echo (int) $s['id']; ?>">
                          <button type="submit" class="btn btn-danger" style="padding:6px 12px;font-size:0.8rem;"><i class="fas fa-user-slash"></i> Deactivate</button>
                        </form>
                      <?php else: ?>
                        <form method="post" action="user_management.php" style="display:inline;" class="activate-form" data-name="<?php echo htmlspecialchars((string) $s['display_name'], ENT_QUOTES, 'UTF-8'); ?>">
                          <input type="hidden" name="action" value="activate">
                          <input type="hidden" name="staff_id" value="<?php echo (int) $s['id']; ?>">
                          <button type="submit" class="btn btn-success" style="padding:6px 12px;font-size:0.8rem;"><i class="fas fa-user-check"></i> Activate</button>
                        </form>
                        <form method="post" action="user_management.php" style="display:inline;" class="purge-form" data-name="<?php echo htmlspecialchars((string) $s['display_name'], ENT_QUOTES, 'UTF-8'); ?>" data-username="<?php echo htmlspecialchars((string) $s['username'], ENT_QUOTES, 'UTF-8'); ?>">
                          <input type="hidden" name="action" value="permanent_delete">
                          <input type="hidden" name="staff_id" value="<?php echo (int) $s['id']; ?>">
                          <button type="submit" class="btn btn-purge" style="padding:6px 12px;font-size:0.8rem;"><i class="fas fa-skull-crossbones"></i> Delete permanently</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

  <script>
  (function () {
    <?php if ($flash !== '' && $flashType === 'success'): ?>
    Swal.fire({
      icon: 'success',
      title: 'Done',
      text: <?php echo json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      confirmButtonColor: '#b30000'
    });
    <?php elseif ($flash !== '' && $flashType === 'error'): ?>
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: <?php echo json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      confirmButtonColor: '#b30000'
    });
    <?php elseif ($flash !== '' && $flashType === 'info'): ?>
    Swal.fire({
      icon: 'info',
      title: 'Notice',
      text: <?php echo json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      confirmButtonColor: '#b30000'
    });
    <?php elseif (!empty($validationErrors)): ?>
    Swal.fire({
      icon: 'info',
      title: 'Please check the form',
      html: <?php echo json_encode(
          '<ul style="text-align:left;margin:0;padding-left:1.2em;">' . implode('', array_map(static function (string $e): string {
              return '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
          }, $validationErrors)) . '</ul>',
          JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
      ); ?>,
      confirmButtonColor: '#b30000'
    });
    <?php endif; ?>

    document.querySelectorAll('.deactivate-form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var name = form.getAttribute('data-name') || 'this user';
        Swal.fire({
          title: 'Deactivate account?',
          html: 'User <strong>' + name + '</strong> will no longer be able to log in.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#c62828',
          cancelButtonColor: '#777',
          confirmButtonText: 'Yes, deactivate'
        }).then(function (r) {
          if (r.isConfirmed) form.submit();
        });
      });
    });

    document.querySelectorAll('.activate-form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var name = form.getAttribute('data-name') || 'this user';
        Swal.fire({
          title: 'Activate account?',
          html: 'User <strong>' + name + '</strong> will be able to sign in again.',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#2e7d32',
          cancelButtonColor: '#777',
          confirmButtonText: 'Yes, activate'
        }).then(function (r) {
          if (r.isConfirmed) form.submit();
        });
      });
    });

    document.querySelectorAll('.purge-form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var name = form.getAttribute('data-name') || 'this user';
        var username = form.getAttribute('data-username') || '';
        Swal.fire({
          title: 'Delete permanently?',
          html: '<p style="text-align:left;margin:0 0 12px 0;">This will <strong>remove the database row</strong> for <strong>' + name + '</strong>. This cannot be undone.</p><p style="text-align:left;margin:0;font-size:14px;">Type the username exactly to confirm.</p>',
          icon: 'error',
          input: 'text',
          inputPlaceholder: 'Username',
          showCancelButton: true,
          confirmButtonColor: '#4a148c',
          cancelButtonColor: '#777',
          confirmButtonText: 'Delete forever',
          inputValidator: function (value) {
            if (!value || String(value).trim() !== username) {
              return 'Username must match exactly.';
            }
            return null;
          }
        }).then(function (r) {
          if (r.isConfirmed) form.submit();
        });
      });
    });
  })();
  </script>
</body>
</html>
<?php ob_end_flush(); ?>

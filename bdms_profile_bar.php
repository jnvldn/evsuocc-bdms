<?php
/**
 * Shared profile ring + role label for signed-in pages (Administrator vs Staff).
 */
declare(strict_types=1);

function bdms_is_administrator(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'administrator';
}

function bdms_is_superadmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
}

/**
 * Emit CSS once per page (call from <head>).
 */
function bdms_profile_bar_print_styles(): void
{
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;
    echo <<<'HTML'
<style id="bdms-profile-bar-styles">
.bdms-profile-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 14px 6px 8px;
  border-radius: 999px;
  background: rgba(255,255,255,0.12);
  border: 2px solid rgba(255,255,255,0.35);
  max-width: 280px;
}
.bdms-profile-ring {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-weight: 700;
  font-size: 1.1rem;
  box-sizing: border-box;
}
.bdms-profile-ring--admin {
  background: linear-gradient(145deg, #fff8e1, #ffe082);
  color: #5d4037;
  border: 3px solid #ffc107;
  box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.35);
}
.bdms-profile-ring--staff {
  background: linear-gradient(145deg, #e3f2fd, #90caf9);
  color: #0d47a1;
  border: 3px solid #42a5f5;
  box-shadow: 0 0 0 2px rgba(66, 165, 245, 0.35);
}
.bdms-profile-ring--superadmin {
  background: linear-gradient(145deg, #ede7f6, #b39ddb);
  color: #311b92;
  border: 3px solid #7e57c2;
  box-shadow: 0 0 0 2px rgba(126, 87, 194, 0.35);
}
.bdms-profile-text {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
  min-width: 0;
  text-align: left;
}
.bdms-profile-name {
  font-size: 0.85rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #fff;
}
.bdms-profile-role {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  opacity: 0.95;
}
.bdms-profile-role--admin { color: #ffe082; }
.bdms-profile-role--staff { color: #bbdefb; }
.bdms-profile-role--superadmin { color: #d1c4e9; }
.bdms-profile-bar--light {
  background: #fff;
  border-color: #e0e0e0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.bdms-profile-bar--light .bdms-profile-name { color: #333; }
.bdms-profile-bar--light .bdms-profile-role--admin { color: #b8860b; }
.bdms-profile-bar--light .bdms-profile-role--staff { color: #1565c0; }
.bdms-profile-bar--light .bdms-profile-role--superadmin { color: #5e35b1; }
.bdms-page-strip {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 20px;
  background: #8b0000;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.bdms-page-strip a {
  color: #fff;
  font-size: 0.85rem;
  text-decoration: none;
  opacity: 0.95;
}
.bdms-page-strip a:hover { text-decoration: underline; }
</style>
HTML;
}

function bdms_profile_bar_render(bool $light_variant = false): void
{
    $role = isset($_SESSION['role']) ? (string) $_SESSION['role'] : 'staff';
    if ($role === 'superadmin') {
        $role_label = 'Super Admin';
        $ring_class = 'bdms-profile-ring--superadmin';
        $role_text_class = 'bdms-profile-role--superadmin';
    } elseif ($role === 'administrator') {
        $role_label = 'Administrator';
        $ring_class = 'bdms-profile-ring--admin';
        $role_text_class = 'bdms-profile-role--admin';
    } else {
        $role_label = 'Staff';
        $ring_class = 'bdms-profile-ring--staff';
        $role_text_class = 'bdms-profile-role--staff';
    }
    $name = '';
    if (!empty($_SESSION['display_name'])) {
        $name = (string) $_SESSION['display_name'];
    } elseif (!empty($_SESSION['user'])) {
        $name = (string) $_SESSION['user'];
    }
    $initial = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
    $bar_extra = $light_variant ? ' bdms-profile-bar--light' : '';
    echo '<div class="bdms-profile-bar' . $bar_extra . '" role="status" aria-label="Signed in as ' . htmlspecialchars($role_label, ENT_QUOTES, 'UTF-8') . '">';
    echo '<div class="bdms-profile-ring ' . htmlspecialchars($ring_class, ENT_QUOTES, 'UTF-8') . '"><span class="bdms-profile-initial">' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</span></div>';
    echo '<div class="bdms-profile-text">';
    echo '<span class="bdms-profile-name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '<span class="bdms-profile-role ' . htmlspecialchars($role_text_class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($role_label, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</div></div>';
}

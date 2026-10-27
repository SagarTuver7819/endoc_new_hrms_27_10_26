<?php
/**
 * Login Page — Admin & Employee portals
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/department_head_helper.php';

$companyName = getCompanyName();
$companyLogo = getLoginLogo();
$hasCustomLogo = isCustomLogo($companyLogo);

if (isLoggedIn()) {
    require_once __DIR__ . '/includes/department_head_helper.php';
    if (empty($_SESSION['role_code']) && !empty($_SESSION['custom_role_id'])) {
        refreshHeadedDepartmentsSession();
    }
    if (function_exists('isOfficeStaffRole') && isOfficeStaffRole()) {
        header('Location: ' . app_url('employee/dashboard.php'));
    } elseif (isHR()) {
        header('Location: ' . app_url('hr/dashboard.php'));
    } elseif (isEmployee()) {
        header('Location: ' . app_url('employee/dashboard.php'));
    } else {
        // Admin / staff → HR Dashboard
        header('Location: ' . app_url('hr/dashboard.php'));
    }
    exit;
}

$error = '';
// UI has 2 choices; Admin covers staff (admin + hr accounts)
$uiRoles = ['admin', 'employee'];
$dbRoles = ['admin', 'hr', 'employee'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    // Match DB usernames case-insensitively (do not force capitals in the form UI)
    if (function_exists('mb_strtoupper')) {
        $username = mb_strtoupper($usernameInput, 'UTF-8');
    } else {
        $username = strtoupper($usernameInput);
    }
    $loginAs = strtolower(trim($_POST['login_as'] ?? 'admin'));
    if (!in_array($loginAs, $uiRoles, true)) {
        $loginAs = 'admin';
    }

    if ($usernameInput === '' || $password === '') {
        $error = 'Please enter username and password.';
        $username = $usernameInput;
    } else {
        ensureRoleTables();
        $conn = getDBConnection();
        $stmt = $conn->prepare(
            "SELECT id, username, password, full_name, role, department_id, custom_role_id, employee_id, status
             FROM users WHERE username = ? AND status = 1 LIMIT 1"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        $roleName = '';
        if ($user && !empty($user['custom_role_id'])) {
            $rs = $conn->prepare('SELECT name FROM roles WHERE id = ? AND status = 1 LIMIT 1');
            $rid = (int) $user['custom_role_id'];
            $rs->bind_param('i', $rid);
            $rs->execute();
            $rr = $rs->get_result()->fetch_assoc();
            $rs->close();
            $roleName = (string) ($rr['name'] ?? '');
        }
        $conn->close();

        $userRole = strtolower((string) ($user['role'] ?? ''));
        $roleOk = false;
        if ($loginAs === 'admin') {
            // Admin portal: Admin + HR staff accounts
            $roleOk = in_array($userRole, ['admin', 'hr'], true);
        } else {
            $roleOk = ($userRole === 'employee');
        }

        if (!$user || $user['password'] !== $password) {
            $error = 'Invalid username or password.';
        } elseif (!in_array($userRole, $dbRoles, true)) {
            $error = 'This account cannot sign in here.';
        } elseif (!$roleOk) {
            if ($loginAs === 'admin') {
                $error = 'Use Employee Login for this account.';
            } else {
                $error = 'Use Admin Login for this account.';
            }
        } elseif ($userRole === 'employee' && (empty($user['custom_role_id']) || $roleName === '')) {
            $error = 'No active role assigned. Contact Admin.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department_id'] = $user['department_id'];
            $_SESSION['employee_id'] = !empty($user['employee_id']) ? (int) $user['employee_id'] : 0;
            $_SESSION['custom_role_id'] = !empty($user['custom_role_id']) ? (int) $user['custom_role_id'] : 0;
            $_SESSION['custom_role_name'] = $roleName;
            unset($_SESSION['permissions'], $_SESSION['permissions_role_id']);
            if ($_SESSION['custom_role_id'] > 0) {
                loadUserPermissionsIntoSession($_SESSION['custom_role_id'], true);
            } else {
                $_SESSION['permissions'] = [];
                $_SESSION['permissions_role_id'] = 0;
            }
            refreshHeadedDepartmentsSession();
            $roleCode = strtoupper((string) ($_SESSION['role_code'] ?? ''));
            $roleCode = strtoupper((string) ($_SESSION['role_code'] ?? ''));
            if ($roleCode === 'DEPT_HEAD') {
                $hd = $_SESSION['headed_department_ids'][0] ?? 0;
                header('Location: ' . app_url($hd > 0 ? ('employees/index.php?department_id=' . (int) $hd) : 'hr/dashboard.php'));
            } elseif ($userRole === 'employee' || $roleCode === 'OFFICE_STAFF') {
                $_SESSION['show_company_popup'] = 1;
                header('Location: ' . app_url('employee/dashboard.php'));
            } else {
                // Admin / HR / staff → HR Dashboard
                header('Location: ' . app_url('hr/dashboard.php'));
            }
            exit;
        }
        // Keep what the user typed in the form (no capital force on login UI)
        $username = $usernameInput;
    }
}

$selectedRole = isset($_POST['login_as']) && in_array($_POST['login_as'], $uiRoles, true)
    ? $_POST['login_as']
    : 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($companyName); ?> HRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css?v=<?php echo filemtime(__DIR__ . '/assets/css/login.css'); ?>">
</head>
<body class="login-page">

<div class="login-bg" aria-hidden="true">
    <div class="login-bg-glow login-bg-glow-a"></div>
    <div class="login-bg-glow login-bg-glow-b"></div>
    <div class="login-bg-glow login-bg-glow-c"></div>
    <div class="login-bg-grid"></div>
    <div class="login-bg-sweep"></div>

    <svg width="0" height="0" style="position:absolute">
        <defs>
            <symbol id="rb-tyre" viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="92" fill="#2b2a29"/>
                <?php for ($i = 0; $i < 36; $i++): ?>
                <rect x="94" y="2" width="12" height="14" rx="2" fill="#2b2a29" transform="rotate(<?php echo $i * 10; ?> 100 100)"/>
                <?php endfor; ?>
                <circle cx="100" cy="100" r="80" fill="none" stroke="#4a4846" stroke-width="3" stroke-dasharray="6 5"/>
                <circle cx="100" cy="100" r="62" fill="#3a3938"/>
                <circle cx="100" cy="100" r="46" fill="#0c54a0"/>
                <circle cx="100" cy="100" r="40" fill="none" stroke="#e7eef8" stroke-width="2"/>
                <?php for ($i = 0; $i < 6; $i++): ?>
                <rect x="96" y="62" width="8" height="34" rx="3" fill="#e7eef8" transform="rotate(<?php echo $i * 60; ?> 100 100)"/>
                <?php endfor; ?>
                <circle cx="100" cy="100" r="13" fill="#e7eef8"/>
                <circle cx="100" cy="100" r="6" fill="#0c54a0"/>
            </symbol>
            <symbol id="rb-recycle" viewBox="0 0 100 100">
                <g fill="none" stroke="#0c54a0" stroke-width="7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M50 14 A36 36 0 0 1 84 40"/>
                    <path d="M82 60 A36 36 0 0 1 40 85"/>
                    <path d="M22 72 A36 36 0 0 1 26 26"/>
                </g>
                <g fill="#0c54a0">
                    <path d="M92 34 L84 48 L74 34 Z"/>
                    <path d="M36 94 L28 80 L44 78 Z"/>
                    <path d="M16 22 L32 18 L30 34 Z"/>
                </g>
            </symbol>
            <symbol id="rb-drop" viewBox="0 0 40 56">
                <path d="M20 2 C20 2 4 24 4 36 a16 16 0 0 0 32 0 C36 24 20 2 20 2Z" fill="#f6f9fd" stroke="#0c54a0" stroke-width="2.5"/>
                <path d="M12 36 a8 8 0 0 0 6 8" fill="none" stroke="#8db8e6" stroke-width="2.5" stroke-linecap="round"/>
            </symbol>
        </defs>
    </svg>

    <svg class="rb-tyre rb-tyre-1"><use href="#rb-tyre"/></svg>
    <svg class="rb-tyre rb-tyre-2"><use href="#rb-tyre"/></svg>
    <svg class="rb-tyre rb-tyre-3"><use href="#rb-tyre"/></svg>
    <svg class="rb-recycle"><use href="#rb-recycle"/></svg>

    <svg class="rb-drop rb-drop-1"><use href="#rb-drop"/></svg>
    <svg class="rb-drop rb-drop-2"><use href="#rb-drop"/></svg>
    <svg class="rb-drop rb-drop-3"><use href="#rb-drop"/></svg>

    <span class="rb-crumb c1"></span>
    <span class="rb-crumb c2"></span>
    <span class="rb-crumb c3"></span>
    <span class="rb-crumb c4"></span>
    <span class="rb-crumb c5"></span>
    <span class="rb-crumb c6"></span>
    <span class="rb-crumb c7"></span>
    <span class="rb-crumb c8"></span>

    <div class="rb-road">
        <svg class="rb-vehicle rb-truck" viewBox="0 0 270 120">
            <rect x="4" y="74" width="176" height="10" rx="3" fill="#2b2a29"/>
            <rect x="8" y="40" width="54" height="32" rx="5" fill="#2b2a29"/>
            <rect x="8" y="66" width="54" height="6" fill="#0c54a0"/>
            <text x="35" y="61" text-anchor="middle" class="rb-bale-txt">BUTYL</text>
            <rect x="66" y="40" width="54" height="32" rx="5" fill="#0c54a0"/>
            <rect x="66" y="66" width="54" height="6" fill="#2b2a29"/>
            <text x="93" y="61" text-anchor="middle" class="rb-bale-txt">EPDM</text>
            <rect x="124" y="40" width="54" height="32" rx="5" fill="#2b2a29"/>
            <rect x="124" y="66" width="54" height="6" fill="#0c54a0"/>
            <text x="151" y="61" text-anchor="middle" class="rb-bale-txt">WTR</text>
            <rect x="37" y="8" width="54" height="30" rx="5" fill="#0c54a0"/>
            <rect x="37" y="32" width="54" height="6" fill="#2b2a29"/>
            <text x="64" y="27" text-anchor="middle" class="rb-bale-txt">NR</text>
            <rect x="95" y="8" width="54" height="30" rx="5" fill="#2b2a29"/>
            <rect x="95" y="32" width="54" height="6" fill="#0c54a0"/>
            <text x="122" y="27" text-anchor="middle" class="rb-bale-txt">RCB</text>
            <path d="M184 84 V38 a8 8 0 0 1 8 -8 h38 l26 26 v28 Z" fill="#0c54a0"/>
            <path d="M200 38 h28 l18 18 h-46 Z" fill="#e7eef8"/>
            <rect x="184" y="62" width="76" height="4" fill="#08407c"/>
            <rect x="254" y="70" width="10" height="8" rx="2" fill="#fbbf24"/>
            <rect x="0" y="84" width="266" height="6" rx="3" fill="#1d1c1b"/>
            <g class="rb-wheel"><circle class="rb-wheel-glow" cx="42" cy="94" r="24"/><svg x="22" y="74" width="40" height="40" viewBox="0 0 200 200" overflow="visible"><g class="rb-wheel-spin"><use href="#rb-tyre" width="200" height="200"/></g></svg></g>
            <g class="rb-wheel"><circle class="rb-wheel-glow" cx="118" cy="94" r="24"/><svg x="98" y="74" width="40" height="40" viewBox="0 0 200 200" overflow="visible"><g class="rb-wheel-spin"><use href="#rb-tyre" width="200" height="200"/></g></svg></g>
            <g class="rb-wheel"><circle class="rb-wheel-glow" cx="222" cy="94" r="24"/><svg x="202" y="74" width="40" height="40" viewBox="0 0 200 200" overflow="visible"><g class="rb-wheel-spin"><use href="#rb-tyre" width="200" height="200"/></g></svg></g>
        </svg>

        <svg class="rb-vehicle rb-car" viewBox="0 0 200 90">
            <path d="M8 63 V51 Q8 44 15 42.5 L42 38 L63 21 Q69 16 79 16 H119 Q129 16 136 21.5 L157 38 L184 42 Q194 44 194 52 V63 Q194 69 188 69 H171 A19 19 0 0 0 133 69 H69 A19 19 0 0 0 31 69 H14 Q8 69 8 63 Z" fill="#2b2a29"/>
            <path d="M49 37 L65 23.5 Q69 20 76 20 H97 V37 Z" fill="#cfe0f3"/>
            <path d="M102 20 H119 Q126 20 131 24 L148 37 H102 Z" fill="#cfe0f3"/>
            <path d="M58 30 L66 23.5 Q68 22 72 21.5 L64 30 Z" fill="#ffffff" opacity="0.7"/>
            <line x1="99.5" y1="38" x2="99.5" y2="66" stroke="#1d1c1b" stroke-width="1.5"/>
            <line x1="46" y1="38" x2="46" y2="62" stroke="#1d1c1b" stroke-width="1.2"/>
            <line x1="150" y1="38" x2="150" y2="62" stroke="#1d1c1b" stroke-width="1.2"/>
            <rect x="84" y="43" width="10" height="2.5" rx="1.2" fill="#8db8e6"/>
            <rect x="134" y="43" width="10" height="2.5" rx="1.2" fill="#8db8e6"/>
            <path d="M148 34 h7 l2 5 h-9 Z" fill="#0c54a0"/>
            <rect x="10" y="51" width="182" height="3.5" fill="#0c54a0"/>
            <path d="M186 45 h6 q3 0 3 3 v3 h-9 Z" fill="#fbbf24"/>
            <rect x="8" y="45" width="5" height="6" rx="1.5" fill="#ef4444"/>
            <rect x="182" y="59" width="13" height="5" rx="2" fill="#1d1c1b"/>
            <rect x="7" y="59" width="12" height="5" rx="2" fill="#1d1c1b"/>
            <g class="rb-wheel"><circle class="rb-wheel-glow" cx="50" cy="69" r="19"/><svg x="34" y="53" width="32" height="32" viewBox="0 0 200 200" overflow="visible"><g class="rb-wheel-spin"><use href="#rb-tyre" width="200" height="200"/></g></svg></g>
            <g class="rb-wheel"><circle class="rb-wheel-glow" cx="152" cy="69" r="19"/><svg x="136" y="53" width="32" height="32" viewBox="0 0 200 200" overflow="visible"><g class="rb-wheel-spin"><use href="#rb-tyre" width="200" height="200"/></g></svg></g>
        </svg>

        <div class="rb-road-surface"></div>
    </div>
</div>

<div class="login-center">
    <div class="login-card anim-in">
        <span class="login-hub-badge">Command Hub</span>

        <header class="login-brand">
            <p class="login-since">Since 1971</p>

            <?php if ($hasCustomLogo): ?>
                <div class="login-logo-wrap anim-logo">
                    <img src="<?php echo htmlspecialchars($companyLogo); ?>?v=<?php echo @filemtime(__DIR__ . '/' . $companyLogo) ?: time(); ?>"
                         alt="<?php echo htmlspecialchars($companyName); ?>"
                         class="login-logo login-logo-custom">
                </div>
            <?php else: ?>
                <div class="login-logo-wrap anim-logo">
                    <div class="login-logo-mark" aria-hidden="true">
                        <img src="assets/images/logo-placeholder.svg" alt="">
                    </div>
                </div>
                <div class="login-wordmark anim-wordmark">
                    <span class="wm-brand">FISHFA</span>
                    <span class="wm-fire">RUBBERS</span>
                    <span class="wm-tag">New Geometry of Rubber</span>
                </div>
            <?php endif; ?>

            <h1 class="login-portal-title">Human Resource Management</h1>
            <p class="login-tagline">Creating Wealth from Waste · Precision ERP Node</p>
        </header>

        <?php if ($error !== ''): ?>
            <div class="login-alert anim-shake" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" class="login-form" autocomplete="off">
            <div class="login-field">
                <label>Login as</label>
                <div class="login-roles login-roles-2" role="radiogroup" aria-label="Login role">
                    <label class="login-role">
                        <input type="radio" name="login_as" value="admin" <?php echo $selectedRole === 'admin' ? 'checked' : ''; ?>>
                        <span class="login-role-box admin">
                            <span class="login-role-icon"><i class="fa-solid fa-user-shield"></i></span>
                            <b>Admin Login</b>
                            <small>Admin &amp; HR staff</small>
                        </span>
                    </label>
                    <label class="login-role">
                        <input type="radio" name="login_as" value="employee" <?php echo $selectedRole === 'employee' ? 'checked' : ''; ?>>
                        <span class="login-role-box employee">
                            <span class="login-role-icon"><i class="fa-solid fa-id-badge"></i></span>
                            <b>Employee Login</b>
                            <small>Staff portal access</small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="login-field">
                <label for="username">Credential ID</label>
                <div class="login-input-wrap">
                    <i class="fa-solid fa-shield-halved"></i>
                    <input type="text" id="username" name="username" placeholder="Credential ID" required
                           autocomplete="username"
                           value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                </div>
            </div>

            <div class="login-field">
                <label for="password">Encryption Key</label>
                <div class="login-input-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Encryption Key" required
                           autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="login-btn" id="btnLogin">
                <span class="login-btn-text">
                    Validate &amp; Initiate Session
                    <i class="fa-solid fa-bolt"></i>
                </span>
                <span class="login-btn-load" hidden>
                    <i class="fa-solid fa-circle-notch fa-spin"></i>
                    Authenticating...
                </span>
            </button>
        </form>

        <footer class="login-card-foot">
            <span class="foot-label">Designed &amp; Developed by</span>
            <a class="foot-brand" href="https://www.oceaninfotech.co.in/" target="_blank" rel="noopener">Ocean Infotech</a>
        </footer>
    </div>

    <p class="login-copy anim-in" style="--delay:.3s">
        © <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?> · Secure Admin &amp; Employee portals
    </p>
</div>

<script src="assets/js/login.js"></script>
</body>
</html>

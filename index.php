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
if ($companyLogo === '' || !is_file(__DIR__ . '/' . ltrim((string) $companyLogo, '/'))) {
    $companyLogo = 'assets/images/endoc-logo.png';
}
$hasLogoFile = is_file(__DIR__ . '/' . ltrim((string) $companyLogo, '/'));

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
    <link rel="icon" type="image/png" href="<?php echo app_url('assets/images/fav.png'); ?>">
    <link rel="shortcut icon" type="image/png" href="<?php echo app_url('assets/images/fav.png'); ?>">
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

    <?php
    // Endoc APIs — https://endocbiotech.com/product
    $beltItems = [
        ['MUPIROCIN', 'API', 'fa-capsules'],
        ['MUPIROCIN Ca', 'API', 'fa-flask'],
        ['TACROLIMUS', 'API', 'fa-prescription-bottle'],
        ['FUSIDIC ACID', 'UP', 'fa-vial'],
        ['CYCLOSPORINE', 'UP', 'fa-syringe'],
        ['DAPTOMYCIN', 'UP', 'fa-pills'],
        ['GMP', 'QC', 'fa-certificate'],
        ['WHO-GMP', 'QC', 'fa-shield-halved'],
    ];
    ?>

    <svg class="bio-wm bio-wm-1" viewBox="0 0 120 120" aria-hidden="true">
        <circle cx="60" cy="28" r="10" fill="#008094"/><circle cx="28" cy="78" r="10" fill="#8B3A36"/>
        <circle cx="92" cy="78" r="10" fill="#006070"/><circle cx="60" cy="60" r="8" fill="#008094"/>
        <line x1="60" y1="28" x2="60" y2="60" stroke="#008094" stroke-width="3"/>
        <line x1="60" y1="60" x2="28" y2="78" stroke="#8B3A36" stroke-width="3"/>
        <line x1="60" y1="60" x2="92" y2="78" stroke="#006070" stroke-width="3"/>
    </svg>
    <svg class="bio-wm bio-wm-2" viewBox="0 0 80 110" aria-hidden="true">
        <path d="M30 8 h20 v28 l22 52 a14 14 0 0 1 -12 20 H20 a14 14 0 0 1 -12 -20 L30 36 Z" fill="none" stroke="#008094" stroke-width="3"/>
        <rect x="28" y="4" width="24" height="8" rx="2" fill="#008094"/>
    </svg>
    <svg class="bio-wm bio-wm-3" viewBox="0 0 64 64" aria-hidden="true">
        <rect x="24" y="6" width="16" height="52" rx="4" fill="#008094" opacity="0.35"/>
        <rect x="6" y="24" width="52" height="16" rx="4" fill="#008094" opacity="0.35"/>
    </svg>

    <div class="bio-belt-scene">
        <div class="bio-desk">
            <div class="bio-desk-glow"></div>
            <div class="bio-monitor">
                <span class="bio-monitor-screen"><i class="fa-solid fa-dna"></i></span>
            </div>
            <div class="bio-desk-body"></div>
            <div class="bio-desk-label">R&amp;D</div>
        </div>

        <div class="bio-belt">
            <div class="bio-belt-track"></div>
            <div class="bio-belt-items">
                <?php foreach ($beltItems as $i => $item): ?>
                    <div class="bio-belt-item bio-bi-<?php echo (int) $i; ?>">
                        <span class="bio-bi-ico"><i class="fa-solid <?php echo htmlspecialchars($item[2]); ?>"></i></span>
                        <strong><?php echo htmlspecialchars($item[0]); ?></strong>
                        <em><?php echo htmlspecialchars($item[1]); ?></em>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($beltItems as $i => $item): ?>
                    <div class="bio-belt-item bio-bi-dup bio-bi-<?php echo (int) $i; ?>">
                        <span class="bio-bi-ico"><i class="fa-solid <?php echo htmlspecialchars($item[2]); ?>"></i></span>
                        <strong><?php echo htmlspecialchars($item[0]); ?></strong>
                        <em><?php echo htmlspecialchars($item[1]); ?></em>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bio-gate">
            <div class="bio-gate-arch">QC</div>
            <div class="bio-gate-badge bio-gate-gmp">GMP</div>
            <div class="bio-gate-badge bio-gate-ready">READY</div>
        </div>
    </div>
</div>

<div class="login-center">
    <div class="login-card anim-in">
        <span class="login-hub-badge">Command Hub</span>

        <header class="login-brand">
            <?php if ($hasLogoFile): ?>
                <div class="login-logo-wrap anim-logo">
                    <img src="<?php echo htmlspecialchars($companyLogo); ?>?v=<?php echo @filemtime(__DIR__ . '/' . $companyLogo) ?: time(); ?>"
                         alt="<?php echo htmlspecialchars($companyName); ?>"
                         class="login-logo login-logo-custom login-logo-endoc">
                </div>
            <?php else: ?>
                <div class="login-wordmark anim-wordmark">
                    <span class="wm-brand">ENDOC</span>
                    <span class="wm-fire">BIOTECH</span>
                    <span class="wm-tag">Pvt Ltd</span>
                </div>
            <?php endif; ?>

            <h1 class="login-portal-title">Human Resource Management</h1>
            <p class="login-tagline">Endoc Biotech Pvt Ltd · HRMS</p>
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

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

    <!-- Soft biotech watermarks (like Fezzan gears) -->
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

    <?php
    // All products from https://endocbiotech.com/product (Commercialized + Upcoming)
    $beltSvg = [
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8.5 3.5a4.5 4.5 0 0 0 0 9h7a4.5 4.5 0 0 0 0-9h-7zm0 2h3.2v5H8.5a2.5 2.5 0 0 1 0-5zm4.8 0H15.5a2.5 2.5 0 0 1 0 5h-2.2V5.5z"/></svg>',
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 2h6v2h-1v5.6l4.7 8.2A2.5 2.5 0 0 1 16.5 22h-9a2.5 2.5 0 0 1-2.2-4.2L10 9.6V4H9V2zm2 7.8-4.3 7.5a.5.5 0 0 0 .4.7h9.8a.5.5 0 0 0 .4-.7L13 9.8V4h-2v5.8z"/></svg>',
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 2h6v3h-1v2.2l1.8 1.4A4 4 0 0 1 18 12v7a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3v-7a4 4 0 0 1 2.2-3.4L10 7.2V5H9V2zm2 5.5-1.3 1A2 2 0 0 0 9 12v7a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-7a2 2 0 0 0-.7-1.5L13 7.5V5h-2v2.5z"/></svg>',
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 2h8v3H8V2zm1 5h6v2.2l3 9.3A2.5 2.5 0 0 1 15.6 22H8.4A2.5 2.5 0 0 1 6 18.5L9 9.2V7zm2 2.4-2.5 7.8a.5.5 0 0 0 .5.7h6a.5.5 0 0 0 .5-.7L13 9.4V9h-2v.4z"/></svg>',
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m19.8 2.2 2 2-2.1 2.1 1.4 1.4-1.4 1.4-1.4-1.4-1.8 1.8 1.1 1.1-1.4 1.4-1.1-1.1-8.2 8.2H4.5L2 22l1.8-2.5V17l8.2-8.2-1.1-1.1 1.4-1.4 1.1 1.1 1.8-1.8-1.4-1.4 1.4-1.4 1.4 1.4 2.1-2.1zM10 14.2 6.8 17.4h2.1L12 14.3 10 14.2z"/></svg>',
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8.5 4A4.5 4.5 0 1 1 8.5 13 4.5 4.5 0 0 1 8.5 4zm0 2a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM15.5 11A4.5 4.5 0 1 1 15.5 20a4.5 4.5 0 0 1 0-9zm0 2a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"/></svg>',
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm0-7.5.9 2.2 2.3-.4-.4 2.3 2.2.9-2.2.9.4 2.3-2.3-.4L12 13.5l-.9-2.2-2.3.4.4-2.3L7 8.5l2.2-.9-.4-2.3 2.3.4L12 2.5zM4.5 14l1.5.6.6 1.5-.6 1.5-1.5.6-1.5-.6L2.4 16l.6-1.5L4.5 14zm15 0 1.5.6.6 1.5-.6 1.5-1.5.6-1.5-.6-.6-1.5.6-1.5 1.5-.6z"/></svg>',
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 2c2.5 2.8 4 5.8 4 10S10.5 19.2 8 22h2c2.2-2.5 3.5-5.4 3.8-8.5.3 3.1 1.6 6 3.8 8.5h2c-2.5-2.8-4-5.8-4-10S17.5 4.8 20 2h-2c-2.2 2.5-3.5 5.4-3.8 8.5C14 7.4 12.7 4.5 10.5 2H8zm1.5 6.5h5v2h-5v-2zm0 5h5v2h-5v-2z"/></svg>',
    ];
    $beltItems = [
        // Commercialized
        ['Mupirocin', 'Commercial', 0],
        ['Mupirocin Calcium', 'Commercial', 1],
        ['Tacrolimus', 'Commercial', 2],
        // Upcoming
        ['Fusidic Acid', 'Upcoming', 3],
        ['Cyclosporine', 'Upcoming', 4],
        ['Daptomycin', 'Upcoming', 5],
        ['Nystatin', 'Upcoming', 6],
        ['Polymyxin B', 'Upcoming', 5],
        ['Polymyxin B Sulfate', 'Upcoming', 0],
        ['Mitomycin', 'Upcoming', 7],
        ['Mycophenolate Mofetil', 'Upcoming', 2],
        ['Mycophenolate Sodium', 'Upcoming', 1],
        ['Micafungin Sodium', 'Upcoming', 3],
        ['Sirolimus', 'Upcoming', 4],
        ['Pimecrolimus', 'Upcoming', 6],
        ['Caspofungin (Acetate)', 'Upcoming', 7],
        ['Teicoplanin', 'Upcoming', 0],
        ['Anidulafungin', 'Upcoming', 1],
        ['Temsirolimus', 'Upcoming', 2],
        ['Everolimus', 'Upcoming', 5],
        ['Voclosporin', 'Upcoming', 4],
    ];
    ?>
    <div class="bio-belt-scene bio-belt-products" aria-hidden="true">
        <div class="bio-lane-lab">
            <span class="bio-lane-ico">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 2h6v2h-1v5.6l4.7 8.2A2.5 2.5 0 0 1 16.5 22h-9a2.5 2.5 0 0 1-2.2-4.2L10 9.6V4H9V2zm2 7.8-4.3 7.5a.5.5 0 0 0 .4.7h9.8a.5.5 0 0 0 .4-.7L13 9.8V4h-2v5.8z"/></svg>
            </span>
            <strong>R&amp;D</strong>
        </div>

        <div class="bio-belt">
            <div class="bio-belt-track"></div>
            <div class="bio-belt-items">
                <?php for ($loop = 0; $loop < 2; $loop++): ?>
                    <?php foreach ($beltItems as $i => $item): ?>
                        <div class="bio-belt-item bio-bi-<?php echo (int) $i; ?>">
                            <span class="bio-bi-ico"><?php echo $beltSvg[(int) $item[2]]; ?></span>
                            <strong><?php echo htmlspecialchars($item[0]); ?></strong>
                            <em><?php echo htmlspecialchars($item[1]); ?></em>
                        </div>
                    <?php endforeach; ?>
                <?php endfor; ?>
            </div>
        </div>

        <div class="bio-lane-qc">
            <span>QC</span>
            <span>GMP</span>
        </div>
    </div>
</div>

<div class="login-center">
    <div class="login-card anim-in">
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

            <span class="login-hub-badge">Secure Login</span>
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
                <label for="username">Username</label>
                <div class="login-input-wrap">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="Enter username" required
                           autocomplete="username"
                           value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                </div>
            </div>

            <div class="login-field">
                <label for="password">Password</label>
                <div class="login-input-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Enter password" required
                           autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="login-btn" id="btnLogin">
                <span class="login-btn-text">
                    Sign In
                    <i class="fa-solid fa-arrow-right"></i>
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

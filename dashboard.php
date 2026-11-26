<?php
/**
 * Dashboard — Workspace hub (quick access)
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/department_head_helper.php';
require_once __DIR__ . '/includes/masters_config.php';

requireLogin();
if (empty($_SESSION['role_code']) && !empty($_SESSION['custom_role_id'])) {
    refreshHeadedDepartmentsSession();
}
// Office Staff → dedicated employee dashboard (not Admin workspace)
if (function_exists('isOfficeStaffRole') && isOfficeStaffRole()) {
    $q = isset($_GET['msg']) ? ('?msg=' . rawurlencode((string) $_GET['msg'])) : '';
    header('Location: ' . app_url('employee/dashboard.php' . $q));
    exit;
}

$pageTitle = 'Dashboard';
// Main workspace: no sidebar (card hub layout, like before)
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];
require_once __DIR__ . '/includes/employee_helper.php';
require_once __DIR__ . '/includes/employee_documents_helper.php';
ensureEmployeeRelatedDocumentsTables();
require_once __DIR__ . '/includes/header.php';

$masterCount = count(getMastersConfig());
$userLabel = function_exists('getUserName') ? getUserName() : 'User';
$hour = (int) date('G');
if ($hour < 12) {
    $greet = 'Good morning';
} elseif ($hour < 17) {
    $greet = 'Good afternoon';
} else {
    $greet = 'Good evening';
}

$pendingDocToasts = [];
$uploadDocToasts = [];
if (isAdmin() || isHR()) {
    foreach (getEmployeesWithPendingRelatedDocuments(6) as $p) {
        $who = trim(($p['employee_code'] !== '' ? $p['employee_code'] . ' · ' : '') . $p['employee_name']);
        $labels = array_slice($p['pending_labels'], 0, 4);
        $more = $p['pending'] - count($labels);
        $list = implode(', ', $labels) . ($more > 0 ? ' +' . $more . ' more' : '');
        $pendingDocToasts[] = $who . ' — pending ' . $p['pending'] . '/' . $p['total'] . ': ' . $list;
    }
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    foreach (fetchUnreadEmployeeDocumentNotifications($uid, 5) as $n) {
        $title = trim((string) ($n['title'] ?? 'Document uploaded'));
        $body = trim((string) ($n['body'] ?? ''));
        $uploadDocToasts[] = $body !== '' ? ($title . ' · ' . $body) : $title;
    }
}
?>

<main class="dashboard-main dash-workspace">
    <div class="dash-hero">
        <div class="dash-hero-copy">
            <p class="dash-eyebrow"><?php echo htmlspecialchars(strtoupper(getCompanyName())); ?> · WORKSPACE</p>
            <h1><?php echo htmlspecialchars($greet); ?>, <?php echo htmlspecialchars($userLabel); ?></h1>
            <p class="dash-sub">Open HR tools, masters, or employee lists.</p>
        </div>
        <div class="dash-hero-stats">
            <div class="dash-stat">
                <span class="dash-stat-num"><?php echo (int) $masterCount; ?></span>
                <span class="dash-stat-lab">Masters</span>
            </div>
        </div>
    </div>

    <section class="module-section dash-section">
        <div class="section-heading">
            <i class="fa-solid fa-bolt"></i>
            <span>QUICK ACCESS</span>
        </div>
        <div class="dash-hub-grid">
            <a href="<?php echo htmlspecialchars(app_url('hr/dashboard.php')); ?>" class="dash-hub-card is-hr" title="Open HR Dashboard">
                <div class="dash-hub-icon" style="background:#0F766E;"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="dash-hub-body">
                    <strong>HR Dashboard</strong>
                    <span>Birthdays · Attendance · Leaves</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <a href="<?php echo htmlspecialchars(app_url('masters/index.php')); ?>" class="dash-hub-card is-masters" title="All Masters">
                <div class="dash-hub-icon" style="background:#008094;"><i class="fa-solid fa-cubes"></i></div>
                <div class="dash-hub-body">
                    <strong>All Masters</strong>
                    <span><?php echo (int) $masterCount; ?> Masters · CRUD</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <a href="<?php echo htmlspecialchars(app_url('employees/index.php')); ?>" class="dash-hub-card is-emp" title="All Employees Report">
                <div class="dash-hub-icon" style="background:#059669;"><i class="fa-solid fa-users"></i></div>
                <div class="dash-hub-body">
                    <strong>All Employees</strong>
                    <span>Active staff directory</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <a href="<?php echo htmlspecialchars(app_url('employees/exit_list.php')); ?>" class="dash-hub-card is-exit" title="Exit Employee List">
                <div class="dash-hub-icon" style="background:#DC2626;"><i class="fa-solid fa-user-xmark"></i></div>
                <div class="dash-hub-body">
                    <strong>Exit Employees</strong>
                    <span>Deactive &amp; exited staff</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?php echo htmlspecialchars(app_url('company_settings.php')); ?>" class="dash-hub-card is-admin" title="Company Logo Setup">
                <div class="dash-hub-icon" style="background:#008094;"><i class="fa-solid fa-image"></i></div>
                <div class="dash-hub-body">
                    <strong>Company Settings</strong>
                    <span>Logo &amp; branding</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
    window.EMP_DOC_PENDING_TOASTS = <?php echo json_encode($pendingDocToasts); ?>;
    window.EMP_DOC_UPLOAD_TOASTS = <?php echo json_encode($uploadDocToasts); ?>;
</script>
<?php
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/emp_docs_pending_toast.js',
];
require_once __DIR__ . '/includes/footer.php';
?>

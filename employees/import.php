<?php
/**
 * Import Employees from Excel / CSV
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/department_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : (int) ($_POST['department_id'] ?? 0);
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if ($deptId > 0 && !$department) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$pageTitle = 'Import Employees';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'employees';
$sidebarDeptId = $deptId;
$sidebarActive = $deptId > 0 ? 'join_employee' : 'all_employees';

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptId = (int) ($_POST['department_id'] ?? $deptId);
    $department = $deptId > 0 ? getDepartmentById($deptId) : null;

    $fileErr = (int) ($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($fileErr !== UPLOAD_ERR_OK || empty($_FILES['import_file']['tmp_name'])) {
        $uploadMessages = [
            UPLOAD_ERR_INI_SIZE => 'File is larger than server upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE => 'File is too large.',
            UPLOAD_ERR_PARTIAL => 'File upload was incomplete. Try again.',
            UPLOAD_ERR_NO_FILE => 'Please choose an Excel / CSV file.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not save uploaded file.',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by a PHP extension.',
        ];
        $error = $uploadMessages[$fileErr] ?? 'Please choose an Excel / CSV file.';
    } else {
        $name = (string) ($_FILES['import_file']['name'] ?? 'upload.csv');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls', 'txt'], true)) {
            $error = 'Only .xlsx, .xls or .csv files are allowed.';
        } elseif ($ext === 'xlsx' && !class_exists('ZipArchive')) {
            $error = 'Excel .xlsx needs PHP Zip extension. Enable extension=zip in php.ini and restart Apache. Or save file as CSV and upload.';
        } else {
            try {
                $conn = getDBConnection();
                $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
                $result = employeeImportFile(
                    $conn,
                    $_FILES['import_file']['tmp_name'],
                    $name,
                    $deptId,
                    $userId
                );
                $conn->close();
                if ($result && (int) $result['success'] === 0 && (int) $result['skipped'] === 0 && (int) $result['errors'] === 0) {
                    $error = 'No rows imported. Check Excel header row and that Sheet1 has data.';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$backUrl = $deptId > 0
    ? app_url('employees/index.php?department_id=' . $deptId)
    : app_url('employees/index.php');
$sampleUrl = app_url('employees/import_sample.php' . ($deptId > 0 ? ('?department_id=' . $deptId) : ''));

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Employee List
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo htmlspecialchars($sampleUrl); ?>" class="btn-secondary">
                <i class="fa-solid fa-download"></i> Example Excel Template
            </a>
            <?php if ($deptId > 0): ?>
                <a href="<?php echo app_url('employees/edit.php?department_id=' . $deptId); ?>" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Employee
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>
                <?php if ($department): ?>
                    Import Employees — <?php echo htmlspecialchars($department['department_name']); ?>
                <?php else: ?>
                    Import Employees — Excel Upload
                <?php endif; ?>
            </h1>
            <p>
                <?php if ($department): ?>
                    All imported rows will be saved in
                    <strong><?php echo htmlspecialchars($department['department_name']); ?></strong>
                    (department is locked for this import)
                <?php else: ?>
                    All departments · Fill <code>department</code> column (exact master name)
                <?php endif; ?>
            </p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="margin-bottom:14px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="alert alert-success" style="margin-bottom:14px;">
                Import complete.
                Added: <strong><?php echo (int) $result['success']; ?></strong>,
                Skipped: <strong><?php echo (int) $result['skipped']; ?></strong>,
                Errors: <strong><?php echo (int) $result['errors']; ?></strong>
            </div>
            <?php if (!empty($result['error_log'])): ?>
                <div class="form-section">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <h3 style="margin:0;"><i class="fa-solid fa-triangle-exclamation" style="color:#d97706;"></i> Skipped &amp; Error Details (વિગતવાર વિગતો)</h3>
                        <span style="font-size:0.85rem;color:#64748b;font-weight:600;"><?php echo count($result['error_log']); ?> Item(s)</span>
                    </div>
                    <div class="table-responsive" style="overflow-x:auto;">
                        <table class="data-table" style="width:100%;font-size:0.88rem;border-collapse:collapse;margin:0;">
                            <thead>
                                <tr style="background:#f1f5f9;text-align:left;">
                                    <th style="padding:10px 12px;border-bottom:2px solid #cbd5e1;width:80px;">Excel Row</th>
                                    <th style="padding:10px 12px;border-bottom:2px solid #cbd5e1;width:95px;">Status</th>
                                    <th style="padding:10px 12px;border-bottom:2px solid #cbd5e1;width:190px;">Employee</th>
                                    <th style="padding:10px 12px;border-bottom:2px solid #cbd5e1;">Problem / Reason (શા કારણે રોકાયું)</th>
                                    <th style="padding:10px 12px;border-bottom:2px solid #cbd5e1;width:240px;">Action / Solution (ઉકેલ)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result['error_log'] as $item): ?>
                                    <?php
                                        $isArr = is_array($item);
                                        $rowNum = $isArr ? ($item['row'] ?? '—') : '';
                                        $type = $isArr ? ($item['type'] ?? 'error') : (stripos($item, 'skip') !== false ? 'skipped' : 'error');
                                        $empName = $isArr ? ($item['name'] ?? '') : '';
                                        $empCode = $isArr ? ($item['code'] ?? '') : '';
                                        $empLabel = trim(($empName ? $empName : '') . ($empCode && $empCode !== '—' ? " ({$empCode})" : ''), ' ()');
                                        $problem = $isArr ? ($item['problem'] ?? '') : $item;
                                        $solution = $isArr ? ($item['solution'] ?? 'Check your Excel row and verify information.') : '';
                                        $badgeBg = ($type === 'skipped') ? '#fef3c7' : '#fee2e2';
                                        $badgeColor = ($type === 'skipped') ? '#92400e' : '#991b1b';
                                        $badgeLabel = ($type === 'skipped') ? 'Skipped' : 'Error';
                                    ?>
                                    <tr style="border-bottom:1px solid #e2e8f0;">
                                        <td style="padding:10px 12px;font-weight:700;color:#0f172a;white-space:nowrap;">Row <?php echo htmlspecialchars((string) $rowNum); ?></td>
                                        <td style="padding:10px 12px;">
                                            <span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:0.75rem;font-weight:700;background:<?php echo $badgeBg; ?>;color:<?php echo $badgeColor; ?>;">
                                                <?php echo $badgeLabel; ?>
                                            </span>
                                        </td>
                                        <td style="padding:10px 12px;font-weight:500;color:#1e293b;"><?php echo htmlspecialchars($empLabel !== '' ? $empLabel : '—'); ?></td>
                                        <td style="padding:10px 12px;color:#334155;line-height:1.5;"><?php echo htmlspecialchars($problem); ?></td>
                                        <td style="padding:10px 12px;color:#0284c7;font-size:0.83rem;line-height:1.45;"><?php echo htmlspecialchars($solution); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <div class="toolbar-actions" style="margin-bottom:16px;">
                <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn-primary">
                    <i class="fa-solid fa-list"></i> View Employee List
                </a>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h3><i class="fa-solid fa-circle-info"></i> How to Import</h3>
            <ol style="line-height:1.7;margin:0;padding-left:18px;">
                <li>Your Excel headers also work:
                    <code>code</code>, <code>Employee Name</code>, <code>Post</code>,
                    <code>Date of Joining</code>, <code>Date of Birth</code>,
                    <code>Mobile Num.</code>, <code>Address</code>, <code>E-mail</code>
                </li>
                <li>Or download the <strong>Example Excel Template</strong></li>
                <li><code>Employee Name</code> is required</li>
                <?php if ($department): ?>
                    <li>Department is fixed to <strong><?php echo htmlspecialchars($department['department_name']); ?></strong> — no department column needed</li>
                <?php else: ?>
                    <li>Add a <code>department</code> column (exact master name), OR open Import from a Department page</li>
                <?php endif; ?>
                <li>Dates: <strong>DD-MM-YYYY</strong> or <strong>YYYY-MM-DD</strong> (Excel dates also OK)</li>
                <li>Blank <code>code</code> → auto employee code (AS… / JW…)</li>
                <li>Upload .xlsx / .xls / .csv and submit</li>
            </ol>
        </div>

        <form method="POST" enctype="multipart/form-data" class="employee-form">
            <input type="hidden" name="department_id" value="<?php echo (int) $deptId; ?>">
            <div class="form-section">
                <h3><i class="fa-solid fa-file-arrow-up"></i> Upload File</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>Import File <span class="req">*</span></label>
                        <input type="file" name="import_file" class="form-control" accept=".xlsx,.xls,.csv,text/csv" required>
                        <small>Supported: Excel (.xls, .xlsx) and CSV. Prefer .xlsx or .csv</small>
                    </div>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-section">
                    <h3>Your Excel → System mapping</h3>
                    <ul style="margin:0;padding-left:18px;line-height:1.7;">
                        <li><code>code</code> → Employee Code</li>
                        <li><code>Employee Name</code> → Name (જ્યાં 3 શબ્દો વાળું નામ હોય દા.ત. Karan Mansukhbhai Senjaliya, ત્યાં વચ્ચેનું નામ ઓટોમેટિક <strong>Father / Husband Name</strong> માં સેવ થશે)</li>
                        <li><code>Post</code> → Employee Type / Designation (new values auto-add in Masters)</li>
                        <li><code>Date of Joining</code> → DOJ</li>
                        <li><code>Date of Birth</code> / <code>Date of Brith</code> → DOB</li>
                        <li><code>Mobile Num.</code> / <code>Mobail Num.</code> → Mobile</li>
                        <li><code>Address</code> → Permanent + Present address</li>
                        <li><code>E-mail</code> → Office Email</li>
                        <?php if ($department): ?>
                            <li>Department locked: <strong><?php echo htmlspecialchars($department['department_name']); ?></strong></li>
                        <?php else: ?>
                            <li><code>department</code> — required when importing from All Employees</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="form-section">
                    <h3>Rules</h3>
                    <ul style="margin:0;padding-left:18px;line-height:1.7;">
                        <li>Duplicate employee code → skipped</li>
                        <li>Blank code → next AS / JW code generated</li>
                        <?php if ($department): ?>
                            <li>Every row is saved under <strong><?php echo htmlspecialchars($department['department_name']); ?></strong></li>
                        <?php else: ?>
                            <li>Invalid department name → error for that row</li>
                        <?php endif; ?>
                        <li>Other filled form fields (bank, PF, address…) are optional</li>
                    </ul>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-file-import"></i> Submit Import
                </button>
                <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Import Salary Details from Excel / CSV
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$department = $deptId > 0 ? getDepartmentById($deptId) : null;

if (!isAdmin() && !isHR() && !canAccess('payroll', 'edit', $deptId) && !canAccess('payroll', 'view', $deptId)) {
    requireAccess('payroll', 'view', $deptId);
}

$conn = getDBConnection();
ensurePayrollTables($conn);
ensureEmployeesTable($conn);
ensureDefaultSalaryComponents($conn);

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        $error = 'Please select a valid Excel or CSV file to upload.';
    } else {
        $origName = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            $error = 'Unsupported file format. Please upload an .xlsx, .xls or .csv file.';
        } else {
            $options = [
                'update_decided_salary' => isset($_POST['opt_decided_salary']),
                'update_department'     => isset($_POST['opt_department']),
                'update_structure'      => isset($_POST['opt_structure']),
                'save_monthly_diary'    => false,
                'save_payslip'          => false,
            ];
            try {
                $result = payrollImportSalarySpreadsheet(
                    $conn,
                    $_FILES['file']['tmp_name'],
                    $origName,
                    (int) date('n'),
                    (int) date('Y'),
                    $options
                );
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Import Salary Details';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'salary_import';

$sampleUrl = app_url('payroll/import_sample.php' . ($deptId > 0 ? ('?department_id=' . $deptId) : ''));
$backUrl = $deptId > 0 ? app_url('payroll/diary.php?department_id=' . $deptId) : app_url('payroll/register.php');

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Payroll
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo htmlspecialchars($sampleUrl); ?>" class="btn-secondary">
                <i class="fa-solid fa-download"></i> Download Example Excel Template
            </a>
            <a href="<?php echo app_url('payroll/diary.php' . ($deptId > 0 ? '?department_id=' . $deptId : '')); ?>" class="btn-secondary">
                <i class="fa-solid fa-layer-group"></i> Salary Structure
            </a>
            <a href="<?php echo app_url('employees/index.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-users"></i> All Employees
            </a>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="login-alert" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;margin-bottom:18px;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <div class="login-alert" style="background:#ecfdf5;border-color:#a7f3d0;color:#047857;margin-bottom:18px;">
            <div style="font-weight:700;font-size:15px;margin-bottom:6px;">
                <i class="fa-solid fa-circle-check"></i> Salary Details Imported Successfully
            </div>
            <div>
                <strong><?php echo (int) $result['success']; ?></strong> employees updated/processed successfully.
                <?php if ($result['skipped'] > 0): ?>
                    · <span style="color:#d97706;"><strong><?php echo (int) $result['skipped']; ?></strong> skipped</span>
                <?php endif; ?>
                <?php if ($result['errors'] > 0): ?>
                    · <span style="color:#dc2626;"><strong><?php echo (int) $result['errors']; ?></strong> errors</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($result['errorLog'])): ?>
            <div class="form-page-card" style="margin-bottom:20px;border-color:#fed7aa;">
                <div class="form-page-header" style="border-bottom:1px solid #ffedd5;padding-bottom:12px;">
                    <div>
                        <h2 style="color:#c2410c;font-size:17px;margin:0 0 4px;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Skipped Rows &amp; Issues Details
                        </h2>
                        <p style="margin:0;font-size:13px;color:#7c2d12;">
                            The following rows had issues in the spreadsheet and were skipped:
                        </p>
                    </div>
                </div>
                <div class="table-wrap" style="margin-top:14px;">
                    <table class="data-table" style="width:100%;">
                        <thead>
                            <tr style="background:#fff7ed;">
                                <th style="width:90px;">Excel Row</th>
                                <th style="width:100px;">Status</th>
                                <th style="width:120px;">Code</th>
                                <th style="width:180px;">Name</th>
                                <th>Problem Description</th>
                                <th>Recommended Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['errorLog'] as $item): ?>
                                <tr>
                                    <td><strong>Row <?php echo (int) $item['row']; ?></strong></td>
                                    <td>
                                        <?php if (($item['type'] ?? '') === 'skipped'): ?>
                                            <span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;background:#fef3c7;color:#92400e;">SKIPPED</span>
                                        <?php else: ?>
                                            <span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;background:#fee2e2;color:#991b1b;">ERROR</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($item['code'] ?? '—'); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($item['name'] ?? '—'); ?></strong></td>
                                    <td style="color:#991b1b;"><?php echo htmlspecialchars($item['problem'] ?? ''); ?></td>
                                    <td style="color:#065f46;font-size:12px;"><?php echo htmlspecialchars($item['solution'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Import Salary Details</h1>
                <p>
                    Upload Excel (.xlsx, .xls) or CSV with employee salary components and decided salary.
                </p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="employee-form" id="salaryImportForm">
            <div class="form-section">
                <h3><i class="fa-solid fa-file-excel"></i> 1. Choose File</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group span-2">
                        <label for="importFile">Excel / CSV File *</label>
                        <input type="file" name="file" id="importFile" class="form-control" accept=".xlsx,.xls,.csv" required>
                        <small class="form-hint">Supported formats: Microsoft Excel (.xlsx, .xls) and CSV (.csv). Sheet 1 is read.</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fa-solid fa-sliders"></i> 2. Import Options</h3>
                <div style="display:flex;flex-direction:column;gap:10px;padding:8px 0;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                        <input type="checkbox" name="opt_decided_salary" value="1" checked>
                        <span><strong>Update Employee Decided Salary</strong> (syncs <code>Actual Salary</code> into Employee Master &amp; Salary History)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                        <input type="checkbox" name="opt_department" value="1" checked>
                        <span><strong>Assign Department from Sheet</strong> (matches Department column with Departments Master)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                        <input type="checkbox" name="opt_structure" value="1" checked>
                        <span><strong>Save Salary Structure Breakdown</strong> (Basic 80%, HRA, Convy, Medi, Other, Allowance, PF, PT in Employee Salary Details)</span>
                    </label>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary" id="btnSubmitImport">
                    <i class="fa-solid fa-file-import"></i> Submit Salary Import
                </button>
                <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>

        <div style="margin-top:32px;padding-top:20px;border-top:1px solid #e2e8f0;">
            <h3 style="font-size:15px;margin-bottom:8px;color:#1e293b;">
                <i class="fa-solid fa-circle-info" style="color:#008094;"></i> Supported Excel Columns Mapping
            </h3>
            <p style="font-size:13px;color:#64748b;margin-bottom:12px;">
                Your spreadsheet can have any of the following column headers. Order does not matter.
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:8px;">
                <?php foreach (payrollImportSalaryHeaders() as $h): ?>
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:6px 10px;border-radius:6px;font-size:12px;">
                        <code style="font-weight:600;color:#0f766e;"><?php echo htmlspecialchars($h); ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>

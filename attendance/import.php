<?php
/**
 * Attendance Import
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();
require_once __DIR__ . '/../includes/permission_helper.php';
requireAccess('attendance', 'add');
ensureAttendanceTables();
@set_time_limit(300);
@ini_set('max_execution_time', '300');
@ini_set('memory_limit', '512M');

$pageTitle = 'Attendance Import';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_import';

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['import_file']['tmp_name'])) {
        $error = 'Please choose an Excel / CSV file.';
    } else {
        $name = (string) ($_FILES['import_file']['name'] ?? 'upload.csv');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls', 'txt'], true)) {
            $error = 'Only .xlsx, .xls or .csv files are allowed.';
        } else {
            try {
                $conn = getDBConnection();
                $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
                $result = attendanceImportFile($conn, $_FILES['import_file']['tmp_name'], $name, $userId);
                $conn->close();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('attendance/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Attendance
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('attendance/history.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-clock-rotate-left"></i> Import History
            </a>
            <a href="<?php echo app_url('attendance/sample.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-download"></i> Sample Excel Template
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Import Attendance — Bulk Upload</h1>
            <p>Upload punch Excel/CSV · Auto-match employees · Sync Salary Diary for salary calculation</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="margin-bottom:14px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <?php
            $okCount = (int) ($result['success'] ?? 0);
            $errCount = (int) ($result['errors'] ?? 0);
            $missing = $result['missing_employees'] ?? [];
            $otherErr = $result['other_errors'] ?? [];
            $alertClass = $errCount > 0 && $okCount > 0 ? 'alert-warning' : ($errCount > 0 ? 'alert-danger' : 'alert-success');
            ?>
            <div class="alert <?php echo $alertClass; ?>" style="margin-bottom:14px;">
                <strong>Import complete</strong> · Batch #<?php echo (int) $result['batch_id']; ?><br>
                Success punches: <strong><?php echo $okCount; ?></strong> ·
                Skipped duplicates: <strong><?php echo (int) $result['skipped']; ?></strong> ·
                Errors: <strong><?php echo $errCount; ?></strong> ·
                Employees synced: <strong><?php echo (int) $result['employees_synced']; ?></strong>
            </div>

            <?php if (!empty($missing)): ?>
                <div class="form-section" style="margin-bottom:16px;">
                    <h3 style="color:#8B3A36;">
                        <i class="fa-solid fa-user-xmark"></i>
                        Employee not found (<?php echo count($missing); ?>)
                    </h3>
                    <p style="margin:0 0 10px;color:#64748b;">
                        Niche na machine ID Employees master ma nathi. Pehla employee add karo
                        (<code>employee_code</code> = machine ID), pachi feri import karo.
                    </p>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Machine ID</th>
                                    <th>Name (report)</th>
                                    <th>Punches skipped</th>
                                    <th>Sample dates</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($missing as $i => $m): ?>
                                    <tr>
                                        <td><?php echo (int) $i + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars((string) ($m['employee_code'] !== '' ? $m['employee_code'] : '—')); ?></strong></td>
                                        <td><?php echo htmlspecialchars((string) ($m['employee_name'] !== '' ? $m['employee_name'] : '—')); ?></td>
                                        <td><?php echo (int) ($m['punches'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars(implode(', ', $m['sample_dates'] ?? [])); ?></td>
                                        <td>
                                            <a class="btn-secondary" style="padding:4px 10px;font-size:12px;"
                                               href="<?php echo app_url('employees/edit.php'); ?>">
                                                Add Employee
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($otherErr)): ?>
                <div class="form-section" style="margin-bottom:16px;">
                    <h3><i class="fa-solid fa-triangle-exclamation"></i> Other errors (<?php echo count($otherErr); ?>)</h3>
                    <ul style="margin:0;padding-left:18px;line-height:1.7;">
                        <?php foreach (array_slice($otherErr, 0, 40) as $oe): ?>
                            <li>
                                <strong>Row <?php echo (int) $oe['row']; ?>:</strong>
                                <?php echo htmlspecialchars((string) $oe['reason']); ?>
                                — <?php echo htmlspecialchars((string) $oe['detail']); ?>
                                <?php if (!empty($oe['employee_code']) || !empty($oe['employee_name'])): ?>
                                    (<?php echo htmlspecialchars(trim(($oe['employee_code'] ?? '') . ' ' . ($oe['employee_name'] ?? ''))); ?>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="form-section">
            <h3><i class="fa-solid fa-circle-info"></i> How to Import</h3>
            <ol style="line-height:1.7;margin:0;padding-left:18px;">
                <li><strong>Biometric Attendance Card Report</strong> (.xls) also supported — e.g. <code>AttendanceCardReport.xls</code></li>
                <li>Or download sample template (flat punch columns)</li>
                <li>Match employees by machine <code>ID</code> = <code>employee_code</code> / <code>biometric_user_id</code></li>
                <li>Upload .xlsx / .xls / .csv and submit</li>
                <li>System rebuilds day status and updates Salary Diary (Present / Week Off)</li>
            </ol>
        </div>

        <form method="POST" enctype="multipart/form-data" class="employee-form">
            <div class="form-section">
                <h3><i class="fa-solid fa-file-arrow-up"></i> Upload File</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>Import File <span class="req">*</span></label>
                        <input type="file" name="import_file" class="form-control" accept=".xlsx,.xls,.csv,text/csv" required>
                        <small>Supported: Excel (.xls, .xlsx) and CSV</small>
                    </div>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-section">
                    <h3>Required Columns</h3>
                    <ul style="margin:0;padding-left:18px;line-height:1.7;">
                        <li><code>employee_code</code> OR <code>biometric_user_id</code> OR <code>employee_name</code></li>
                        <li><code>attendance_date</code> — DD-MM-YYYY or YYYY-MM-DD</li>
                        <li><code>punch_in_time</code> — HH:MM or HH:MM:SS</li>
                        <li><code>attendance_type</code> — in / out (optional)</li>
                        <li><code>punch_out_time</code> — optional same-row out punch</li>
                    </ul>
                </div>
                <div class="form-section">
                    <h3>Common Errors</h3>
                    <ul style="margin:0;padding-left:18px;line-height:1.7;">
                        <li>Employee not found (check code / biometric id)</li>
                        <li>Invalid date/time formats</li>
                        <li>Duplicate punches (auto-skipped)</li>
                        <li>Missing required fields</li>
                    </ul>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Submit Import</button>
                <a href="<?php echo app_url('attendance/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

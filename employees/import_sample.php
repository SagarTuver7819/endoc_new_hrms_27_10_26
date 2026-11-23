<?php
/**
 * Example Employee Import Excel — same structure as endoc employee sheet
 * SR No | Employee Code | NAME | Department | Direct/Indirect/R&D | Staff or Worker
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$deptName = '';
$omitDepartment = false;
if ($deptId > 0) {
    $dept = getDepartmentById($deptId);
    if ($dept) {
        $deptName = (string) $dept['department_name'];
        $omitDepartment = true;
    }
}
if ($deptName === '') {
    $deptName = 'Production';
}

$safeDept = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $deptName);
$filename = $omitDepartment
    ? ('Employee_Import_' . $safeDept . '_' . date('Ymd') . '.xls')
    : ('endoc_employee_sheet_sample_' . date('Ymd') . '.xls');
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$headers = employeeImportHeaders($omitDepartment);

// Sample rows mirror real endoc employee sheet values
if ($omitDepartment) {
    $sample = [
        ['1', '1005', 'SAMPLE EMPLOYEE ONE', 'RnD', 'Office Staff'],
        ['2', '1006', 'SAMPLE EMPLOYEE TWO', 'General', 'Office Staff'],
        ['3', '', 'SAMPLE WORKER THREE', 'Production', 'Worker'],
    ];
} else {
    $sample = [
        ['1', '1005', 'SAMPLE EMPLOYEE ONE', 'RnD', 'RnD', 'Office Staff'],
        ['2', '1006', 'SAMPLE EMPLOYEE TWO', 'Engineering', 'General', 'Office Staff'],
        ['3', '1008', 'SAMPLE EMPLOYEE THREE', 'Production', 'Production', 'Office Staff'],
        ['4', '', 'SAMPLE WORKER FOUR', 'Production', 'Production', 'Worker'],
    ];
}

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1">';
echo '<tr>';
foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';
foreach ($sample as $row) {
    echo '<tr>';
    foreach ($row as $cell) {
        echo '<td>' . htmlspecialchars((string) $cell) . '</td>';
    }
    echo '</tr>';
}
echo '</table>';
echo '<p>Same fields as <strong>endoc employee sheet</strong>: SR No, Employee Code, NAME, Department, Direct/Indirect/R&amp;D, Staff or Worker. Blank Employee Code = auto. Duplicate code/name/Aadhaar/mobile = skipped or updated.</p>';
echo '</body></html>';
exit;

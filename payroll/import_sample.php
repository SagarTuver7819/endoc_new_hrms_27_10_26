<?php
/**
 * Example Salary Import Excel (HTML .xls) template
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$filename = 'Salary_Import_Template_' . date('Ymd') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$headers = payrollImportSalaryHeaders();

$conn = getDBConnection();
ensureEmployeesTable($conn);

$where = "WHERE e.status = 1";
if ($deptId > 0) {
    $where .= " AND e.department_id = " . (int) $deptId;
}

$sql = "SELECT e.id, e.employee_code, e.employee_name, e.decided_salary, d.department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        $where
        ORDER BY e.employee_code ASC
        LIMIT 50";
$res = $conn->query($sql);
$rows = [];
$sr = 1;

if ($res && $res->num_rows > 0) {
    while ($emp = $res->fetch_assoc()) {
        $actual = (float) ($emp['decided_salary'] ?? 0);
        if ($actual <= 0) {
            $actual = 18000.00;
        }
        $workDays = 26;
        $perDay = round($actual / $workDays, 2);
        $totalSal = $actual;
        $cashUpad = 0;
        $payableSal = $actual;
        $basic80 = round($actual * 0.80, 2);
        $hra = round($actual * 0.10, 2);
        $convy = round($actual * 0.05, 2);
        $medi = round($actual * 0.05, 2);
        $other = 0;
        $allowance = 0;
        $pf = 1800.00;
        $pt = ($actual >= 12000) ? 200.00 : 0.00;
        $upad = 0;
        $loan = 0;
        $tds = 0;
        $netBank = $actual - ($pf + $pt);
        $actual9hrs = $actual;
        $netPayable = $netBank;
        $otLoss = 0;

        $rows[] = [
            $sr++,
            $emp['employee_code'],
            $emp['employee_name'],
            $emp['department_name'] ?: 'Production',
            'Direct',
            'Worker',
            number_format($actual, 2, '.', ''),
            $workDays,
            number_format($perDay, 2, '.', ''),
            number_format($totalSal, 2, '.', ''),
            number_format($cashUpad, 2, '.', ''),
            number_format($payableSal, 2, '.', ''),
            number_format($basic80, 2, '.', ''),
            number_format($hra, 2, '.', ''),
            number_format($convy, 2, '.', ''),
            number_format($medi, 2, '.', ''),
            number_format($other, 2, '.', ''),
            number_format($allowance, 2, '.', ''),
            number_format($pf, 2, '.', ''),
            number_format($pt, 2, '.', ''),
            number_format($upad, 2, '.', ''),
            number_format($loan, 2, '.', ''),
            number_format($tds, 2, '.', ''),
            number_format($netBank, 2, '.', ''),
            number_format($actual9hrs, 2, '.', ''),
            number_format($netPayable, 2, '.', ''),
            number_format($otLoss, 2, '.', ''),
        ];
    }
} else {
    // Fallback sample row
    $rows[] = [
        1, '1005', 'Sample Employee', 'Production', 'Direct', 'Staff',
        '20000.00', '26', '769.23', '20000.00', '0.00', '20000.00',
        '16000.00', '2000.00', '1000.00', '1000.00', '0.00', '0.00',
        '1800.00', '200.00', '0.00', '0.00', '0.00', '18000.00',
        '20000.00', '18000.00', '0.00'
    ];
}
$conn->close();

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
echo 'th { background: #1e3a5f; color: #fff; font-weight: bold; border: 1px solid #cbd5e1; padding: 6px 10px; font-size: 13px; white-space: nowrap; }';
echo 'td { border: 1px solid #cbd5e1; padding: 5px 8px; font-size: 12px; }';
echo '.num { text-align: right; }';
echo '</style></head><body>';
echo '<table border="1" cellspacing="0" cellpadding="4">';
echo '<thead><tr>';
foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h) . '</th>';
}
echo '</tr></thead><tbody>';
foreach ($rows as $row) {
    echo '<tr>';
    foreach ($row as $idx => $cell) {
        $isNum = ($idx >= 6);
        echo '<td' . ($isNum ? ' class="num"' : '') . '>' . htmlspecialchars((string) $cell) . '</td>';
    }
    echo '</tr>';
}
echo '</tbody></table></body></html>';

<?php
/**
 * Canonical Fishfa Rubbers departments (reclaim rubber / rCB plant) + duplicate merge / alias resolution.
 */

require_once __DIR__ . '/department_icons.php';

function canonicalDepartmentsCatalog()
{
    return [
        ['ADMINISTRATION', 'fa-landmark', '#1E56A0', 1],
        ['HUMAN RESOURCE MANAGEMENT', 'fa-users', '#0C54A0', 2],
        ['ACCOUNTS AND FINANCE', 'fa-file-invoice-dollar', '#2ECC71', 3],
        ['SALES & MARKETING - DOMESTIC', 'fa-handshake', '#1ABC9C', 4],
        ['EXPORT & INTERNATIONAL MARKETING', 'fa-earth-asia', '#0984E3', 5],
        ['IT AND NETWORKING', 'fa-network-wired', '#3498DB', 6],
        ['PURCHASE', 'fa-cart-shopping', '#2980B9', 7],
        ['STORE', 'fa-warehouse', '#D35400', 8],
        ['QA AND QC', 'fa-clipboard-check', '#16A085', 9],
        ['LABORATORY / R&D', 'fa-flask', '#27AE60', 10],
        ['COMPLIANCE & CERTIFICATION', 'fa-certificate', '#0D9488', 11],
        ['EHS (ENVIRONMENT, HEALTH & SAFETY)', 'fa-leaf', '#00B894', 12],
        ['PRODUCTION PLANNING', 'fa-calendar-check', '#6C5CE7', 13],
        ['RAW MATERIAL YARD', 'fa-dumpster', '#7F8C8D', 14],
        ['SORTING & CUTTING', 'fa-scissors', '#34495E', 15],
        ['CRACKING & GRINDING', 'fa-gear', '#95A5A6', 16],
        ['DEVULCANIZATION', 'fa-temperature-high', '#E74C3C', 17],
        ['MIXING', 'fa-blender', '#9B59B6', 18],
        ['REFINING', 'fa-layer-group', '#8E44AD', 19],
        ['STRAINER', 'fa-filter', '#F39C12', 20],
        ['BUTYL RECLAIM', 'fa-circle-dot', '#1E56A0', 21],
        ['EPDM RECLAIM', 'fa-ring', '#2C3E50', 22],
        ['WHOLE TIRE & NATURAL TUBE RECLAIM', 'fa-life-ring', '#636E72', 23],
        ['rCB / PYROLYSIS PLANT', 'fa-industry', '#2D3436', 24],
        ['PACKING', 'fa-box', '#E67E22', 25],
        ['DISPATCH & LOGISTICS', 'fa-truck', '#00B894', 26],
        ['MECHANICAL MAINTENANCE', 'fa-wrench', '#FD79A8', 27],
        ['ELECTRICAL', 'fa-bolt', '#F1C40F', 28],
        ['BOILER & UTILITY', 'fa-fire-burner', '#C0392B', 29],
        ['SECURITY', 'fa-shield-halved', '#5B6CFF', 30],
        ['HOUSEKEEPING', 'fa-broom', '#3498DB', 31],
        ['CANTEEN', 'fa-utensils', '#FDCB6E', 32],
    ];
}

function normalizeDepartmentKey($name)
{
    $name = strtoupper(trim(html_entity_decode(strip_tags((string) $name))));
    $name = str_replace(['&', '-', '_', '/', '\\', '.', ','], ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function departmentAliasMap()
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $raw = [
        'HUMAN RESOURCE' => 'HUMAN RESOURCE MANAGEMENT',
        'HR ADMIN' => 'HUMAN RESOURCE MANAGEMENT',
        'HR AND ADMIN' => 'HUMAN RESOURCE MANAGEMENT',
        'HR MANAGEMENT' => 'HUMAN RESOURCE MANAGEMENT',
        'ACCOUNTS' => 'ACCOUNTS AND FINANCE',
        'ACCOUNT' => 'ACCOUNTS AND FINANCE',
        'ACCOUNTS AND FINACE' => 'ACCOUNTS AND FINANCE',
        'FINANCE' => 'ACCOUNTS AND FINANCE',
        'SALES AND MARKETING' => 'SALES & MARKETING - DOMESTIC',
        'SALES' => 'SALES & MARKETING - DOMESTIC',
        'MARKETING' => 'SALES & MARKETING - DOMESTIC',
        'EXPORT' => 'EXPORT & INTERNATIONAL MARKETING',
        'EXPORTS' => 'EXPORT & INTERNATIONAL MARKETING',
        'INTERNATIONAL MARKETING' => 'EXPORT & INTERNATIONAL MARKETING',
        'INFORMATION TECHNOLOGY' => 'IT AND NETWORKING',
        'IT NETWORKING' => 'IT AND NETWORKING',
        'IT AND NETWORK' => 'IT AND NETWORKING',
        'QAQC' => 'QA AND QC',
        'QA QC' => 'QA AND QC',
        'QUALITY' => 'QA AND QC',
        'QUALITY ASSURANCE' => 'QA AND QC',
        'QUALITY CONTROL' => 'QA AND QC',
        'LAB' => 'LABORATORY / R&D',
        'LABORATORY' => 'LABORATORY / R&D',
        'R&D' => 'LABORATORY / R&D',
        'EHS' => 'EHS (ENVIRONMENT, HEALTH & SAFETY)',
        'SAFETY' => 'EHS (ENVIRONMENT, HEALTH & SAFETY)',
        'ENVIRONMENT' => 'EHS (ENVIRONMENT, HEALTH & SAFETY)',
        'PPC' => 'PRODUCTION PLANNING',
        'GRINDING' => 'CRACKING & GRINDING',
        'CRACKER' => 'CRACKING & GRINDING',
        'DEVULCANIZER' => 'DEVULCANIZATION',
        'REFINER' => 'REFINING',
        'PYROLYSIS' => 'rCB / PYROLYSIS PLANT',
        'RCB' => 'rCB / PYROLYSIS PLANT',
        'DISPATCH' => 'DISPATCH & LOGISTICS',
        'LOGISTICS' => 'DISPATCH & LOGISTICS',
        'TRANSPORT' => 'DISPATCH & LOGISTICS',
        'MAINTENANCE' => 'MECHANICAL MAINTENANCE',
        'UTILITY' => 'BOILER & UTILITY',
        'BOILER' => 'BOILER & UTILITY',
        'STORE AND PURCHASE' => 'PURCHASE',
        'STORE PURCHASE' => 'PURCHASE',
    ];

    $map = [];
    foreach ($raw as $alias => $canonical) {
        $map[normalizeDepartmentKey($alias)] = $canonical;
    }
    foreach (canonicalDepartmentsCatalog() as $row) {
        $map[normalizeDepartmentKey($row[0])] = $row[0];
    }
    return $map;
}

/**
 * Map any department label to one of the canonical names, or empty if unknown.
 */
function resolveCanonicalDepartmentName($name)
{
    $name = trim(html_entity_decode(strip_tags((string) $name)));
    if ($name === '') {
        return '';
    }

    $key = normalizeDepartmentKey($name);
    $aliases = departmentAliasMap();
    if (isset($aliases[$key])) {
        return $aliases[$key];
    }

    foreach (canonicalDepartmentsCatalog() as $row) {
        $canonical = $row[0];
        $cKey = normalizeDepartmentKey($canonical);
        if ($key === $cKey) {
            return $canonical;
        }
        if ($key !== '' && (strpos($key, $cKey) !== false || strpos($cKey, $key) !== false)) {
            return $canonical;
        }
    }

    return '';
}

function getDepartmentIdByName($conn, $departmentName)
{
    $stmt = $conn->prepare('SELECT id FROM departments WHERE department_name = ? LIMIT 1');
    $stmt->bind_param('s', $departmentName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['id'] : 0;
}

function ensureCanonicalDepartments($conn)
{
    $inserted = 0;
    $stmt = $conn->prepare(
        'INSERT INTO departments (department_name, icon_class, icon_color, sort_order, status)
         SELECT ?, ?, ?, ?, 1 FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM departments WHERE department_name = ? LIMIT 1)'
    );

    foreach (canonicalDepartmentsCatalog() as $row) {
        [$name, $icon, $color, $sort] = $row;
        $stmt->bind_param('ssiis', $name, $icon, $color, $sort, $name);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $inserted++;
        }
    }
    $stmt->close();

    $upd = $conn->prepare(
        'UPDATE departments SET icon_class = ?, icon_color = ?, sort_order = ?, status = 1
         WHERE department_name = ?'
    );
    foreach (canonicalDepartmentsCatalog() as $row) {
        [$name, $icon, $color, $sort] = $row;
        $upd->bind_param('ssis', $icon, $color, $sort, $name);
        $upd->execute();
    }
    $upd->close();

    return $inserted;
}

function reassignDepartmentForeignKeys($conn, $fromId, $toId)
{
    if ($fromId <= 0 || $toId <= 0 || $fromId === $toId) {
        return ['employees' => 0, 'users' => 0, 'sub_departments' => 0, 'contractor_employment' => 0];
    }

    $counts = ['employees' => 0, 'users' => 0, 'sub_departments' => 0, 'contractor_employment' => 0];

    $stmt = $conn->prepare('UPDATE employees SET department_id = ? WHERE department_id = ?');
    $stmt->bind_param('ii', $toId, $fromId);
    $stmt->execute();
    $counts['employees'] = $stmt->affected_rows;
    $stmt->close();

    $stmt = $conn->prepare('UPDATE users SET department_id = ? WHERE department_id = ?');
    $stmt->bind_param('ii', $toId, $fromId);
    $stmt->execute();
    $counts['users'] = $stmt->affected_rows;
    $stmt->close();

    $res = $conn->query(
        "SELECT id, name FROM sub_departments WHERE department_id = {$fromId} AND status = 1"
    );
    if ($res) {
        while ($sub = $res->fetch_assoc()) {
            $subId = (int) $sub['id'];
            $subName = (string) $sub['name'];
            $targetSubId = 0;
            $find = $conn->prepare(
                'SELECT id FROM sub_departments
                 WHERE department_id = ? AND name = ? AND status = 1 LIMIT 1'
            );
            $find->bind_param('is', $toId, $subName);
            $find->execute();
            $existing = $find->get_result()->fetch_assoc();
            $find->close();
            if ($existing) {
                $targetSubId = (int) $existing['id'];
                $conn->query("UPDATE sub_departments SET status = 0 WHERE id = {$subId}");
            } else {
                $move = $conn->prepare('UPDATE sub_departments SET department_id = ? WHERE id = ?');
                $move->bind_param('ii', $toId, $subId);
                $move->execute();
                $move->close();
                $targetSubId = $subId;
                $counts['sub_departments']++;
            }
            if ($targetSubId > 0) {
                $fix = $conn->prepare(
                    'UPDATE employees SET sub_department_id = ? WHERE sub_department_id = ?'
                );
                $fix->bind_param('ii', $targetSubId, $subId);
                $fix->execute();
                $fix->close();
            }
        }
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'contractor_employment'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $stmt = $conn->prepare(
            'UPDATE contractor_employment SET department_id = ? WHERE department_id = ?'
        );
        $stmt->bind_param('ii', $toId, $fromId);
        $stmt->execute();
        $counts['contractor_employment'] = $stmt->affected_rows;
        $stmt->close();
    }

    return $counts;
}

/**
 * Merge duplicate / alias departments into the canonical set.
 * Only merges known aliases / exact duplicate names.
 * Does NOT remove extra legitimate departments.
 */
function mergeDuplicateDepartments($conn)
{
    ensureCanonicalDepartments($conn);

    $canonicalNames = [];
    foreach (canonicalDepartmentsCatalog() as $row) {
        $canonicalNames[$row[0]] = true;
    }

    $log = [];
    $merged = 0;
    $deactivated = 0;

    $res = $conn->query(
        'SELECT id, department_name, status FROM departments ORDER BY id ASC'
    );
    if (!$res) {
        return ['ok' => false, 'log' => ['Could not read departments table.'], 'active' => 0];
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    $canonicalIds = [];
    foreach (canonicalDepartmentsCatalog() as $row) {
        $id = getDepartmentIdByName($conn, $row[0]);
        if ($id > 0) {
            $canonicalIds[$row[0]] = $id;
            // Reactivate canonical rows if previously soft-deleted
            $conn->query('UPDATE departments SET status = 1 WHERE id = ' . (int) $id);
        }
    }

    $seenCanonical = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $name = (string) $row['department_name'];
        $canonical = resolveCanonicalDepartmentName($name);

        // Only merge when name maps to a different canonical department id
        if ($canonical !== '' && isset($canonicalIds[$canonical])) {
            $targetId = $canonicalIds[$canonical];
            if ($id === $targetId) {
                if (!isset($seenCanonical[$canonical])) {
                    $seenCanonical[$canonical] = $id;
                }
                continue;
            }
            // Do not merge a different real department into another just because of partial name match
            // unless the alias map explicitly mapped it (exact normalize key).
            $aliases = departmentAliasMap();
            $key = normalizeDepartmentKey($name);
            $explicitAlias = isset($aliases[$key]) && $aliases[$key] === $canonical && $name !== $canonical;
            $exactDup = (normalizeDepartmentKey($name) === normalizeDepartmentKey($canonical) && $name !== $canonical);
            if (!$explicitAlias && !$exactDup && !isset($canonicalNames[$name])) {
                // Keep unmatched/extra department as-is
                continue;
            }
            if ($name === $canonical) {
                // Same display name duplicate rows
            }
            $counts = reassignDepartmentForeignKeys($conn, $id, $targetId);
            $conn->query("UPDATE departments SET status = 0 WHERE id = {$id}");
            $merged++;
            $deactivated++;
            $log[] = "Merged \"{$name}\" (id {$id}) → \"{$canonical}\" (id {$targetId}); "
                . "employees {$counts['employees']}, users {$counts['users']}.";
            continue;
        }

        // Extra departments (not in canonical list) are kept active — do not force to ADMINISTRATION
    }

    foreach ($rows as $row) {
        $name = (string) $row['department_name'];
        if (!isset($canonicalNames[$name])) {
            continue;
        }
        $canonical = $name;
        if (!isset($seenCanonical[$canonical])) {
            $seenCanonical[$canonical] = (int) $row['id'];
            continue;
        }
        $keepId = (int) $seenCanonical[$canonical];
        $dupId = (int) $row['id'];
        if ($dupId === $keepId || (int) $row['status'] !== 1) {
            continue;
        }
        $counts = reassignDepartmentForeignKeys($conn, $dupId, $keepId);
        $conn->query("UPDATE departments SET status = 0 WHERE id = {$dupId}");
        $merged++;
        $deactivated++;
        $log[] = "Duplicate canonical \"{$name}\" (id {$dupId}) merged into id {$keepId}; "
            . "employees {$counts['employees']}.";
    }

    syncDepartmentIcons($conn);

    $active = (int) $conn->query('SELECT COUNT(*) AS c FROM departments WHERE status = 1')
        ->fetch_assoc()['c'];

    if ($merged === 0 && $deactivated === 0) {
        $log[] = 'Canonical departments ensured. Active: ' . $active . '.';
    } else {
        $log[] = "Done: {$merged} merge(s), {$deactivated} deactivated. Active departments: {$active}.";
    }

    return ['ok' => true, 'log' => $log, 'active' => $active, 'merged' => $merged];
}

/**
 * Used by employee sync — never create alias department rows.
 */
function ensureDepartmentByName($conn, $name)
{
    $name = trim(html_entity_decode(strip_tags((string) $name)));
    if ($name === '') {
        $name = 'General';
    }

    $canonical = resolveCanonicalDepartmentName($name);
    if ($canonical !== '') {
        $name = $canonical;
    }

    $id = getDepartmentIdByName($conn, $name);
    if ($id > 0) {
        return $id;
    }

    foreach (canonicalDepartmentsCatalog() as $row) {
        if ($row[0] === $name) {
            [$n, $icon, $color, $sort] = $row;
            $ins = $conn->prepare(
                'INSERT INTO departments (department_name, icon_class, icon_color, sort_order, status)
                 VALUES (?, ?, ?, ?, 1)'
            );
            $ins->bind_param('sssi', $n, $icon, $color, $sort);
            $ins->execute();
            $id = (int) $conn->insert_id;
            $ins->close();
            return $id;
        }
    }

    [$icon, $color] = resolveDepartmentIcon($name);
    $ins = $conn->prepare(
        'INSERT INTO departments (department_name, icon_class, icon_color, sort_order, status)
         VALUES (?, ?, ?, 100, 1)'
    );
    $ins->bind_param('sss', $name, $icon, $color);
    $ins->execute();
    $id = (int) $conn->insert_id;
    $ins->close();
    return $id;
}

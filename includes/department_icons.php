<?php
/**
 * Department dashboard icons — unique FA6 solid icons + colors by department name
 */

function departmentIconCatalog()
{
    return [
        'ADMINISTRATION' => ['fa-landmark', '#008094'],
        'HUMAN RESOURCE MANAGEMENT' => ['fa-users', '#008094'],
        'ACCOUNTS AND FINANCE' => ['fa-file-invoice-dollar', '#2ECC71'],
        'SALES & MARKETING - DOMESTIC' => ['fa-handshake', '#1ABC9C'],
        'EXPORT & INTERNATIONAL MARKETING' => ['fa-earth-asia', '#0984E3'],
        'IT AND NETWORKING' => ['fa-network-wired', '#3498DB'],
        'PURCHASE' => ['fa-cart-shopping', '#2980B9'],
        'STORE' => ['fa-warehouse', '#D35400'],
        'QA AND QC' => ['fa-clipboard-check', '#16A085'],
        'LABORATORY / R&D' => ['fa-flask', '#27AE60'],
        'COMPLIANCE & CERTIFICATION' => ['fa-certificate', '#0D9488'],
        'EHS (ENVIRONMENT, HEALTH & SAFETY)' => ['fa-leaf', '#00B894'],
        'PRODUCTION PLANNING' => ['fa-calendar-check', '#6C5CE7'],
        'PRODUCTION' => ['fa-industry', '#2D3436'],
        'FORMULATION' => ['fa-flask-vial', '#9B59B6'],
        'PROCESS / MANUFACTURING' => ['fa-gears', '#34495E'],
        'FILLING & PACKAGING' => ['fa-box', '#E67E22'],
        'WAREHOUSE' => ['fa-warehouse', '#7F8C8D'],
        'PACKING' => ['fa-box', '#E67E22'],
        'DISPATCH & LOGISTICS' => ['fa-truck', '#00B894'],
        'MECHANICAL MAINTENANCE' => ['fa-wrench', '#FD79A8'],
        'ELECTRICAL' => ['fa-bolt', '#F1C40F'],
        'UTILITY' => ['fa-fire-burner', '#C0392B'],
        'BOILER & UTILITY' => ['fa-fire-burner', '#C0392B'],
        'SECURITY' => ['fa-shield-halved', '#5B6CFF'],
        'HOUSEKEEPING' => ['fa-broom', '#3498DB'],
        'CANTEEN' => ['fa-utensils', '#FDCB6E'],
        'MANAGEMENT' => ['fa-briefcase', '#008094'],
        'HUMAN RESOURCE' => ['fa-users', '#008094'],
        'INFORMATION TECHNOLOGY' => ['fa-laptop-code', '#3498DB'],
        'QUALITY' => ['fa-certificate', '#16A085'],
    ];
}

function resolveDepartmentIcon($departmentName)
{
    $name = strtoupper(trim(html_entity_decode((string) $departmentName)));
    $name = preg_replace('/\s+/', ' ', $name);
    $catalog = departmentIconCatalog();

    if (isset($catalog[$name])) {
        return $catalog[$name];
    }

    // Partial / alias match
    foreach ($catalog as $key => $meta) {
        if ($name !== '' && (strpos($name, $key) !== false || strpos($key, $name) !== false)) {
            return $meta;
        }
    }

    // Keyword fallbacks so sync-created depts never stay plain building
    $keywords = [
        'CLEAN' => ['fa-broom', '#3498DB'],
        'BIO' => ['fa-flask', '#27AE60'],
        'FORMULA' => ['fa-flask-vial', '#9B59B6'],
        'PROCESS' => ['fa-gears', '#34495E'],
        'EXPORT' => ['fa-earth-asia', '#0984E3'],
        'SALES' => ['fa-handshake', '#1ABC9C'],
        'ACCOUNT' => ['fa-file-invoice-dollar', '#2ECC71'],
        'HR' => ['fa-users', '#008094'],
        'HUMAN' => ['fa-users', '#008094'],
        'IT ' => ['fa-laptop-code', '#3498DB'],
        'TECH' => ['fa-laptop-code', '#3498DB'],
        'MAINT' => ['fa-wrench', '#FD79A8'],
        'CANTEEN' => ['fa-utensils', '#FDCB6E'],
        'DISPATCH' => ['fa-truck', '#00B894'],
        'TRANSPORT' => ['fa-truck-fast', '#636E72'],
        'STORE' => ['fa-warehouse', '#D35400'],
        'PURCHASE' => ['fa-cart-shopping', '#2980B9'],
        'GRIND' => ['fa-gear', '#95A5A6'],
        'CUT' => ['fa-scissors', '#34495E'],
        'BOILER' => ['fa-fire-burner', '#C0392B'],
        'ELECTRIC' => ['fa-bolt', '#F1C40F'],
        'ADMIN' => ['fa-landmark', '#008094'],
        'MANAGE' => ['fa-briefcase', '#008094'],
        'SECURITY' => ['fa-shield-halved', '#2D3436'],
        'QUALITY' => ['fa-clipboard-check', '#16A085'],
        'PACK' => ['fa-box', '#E17055'],
        'LAB' => ['fa-flask', '#27AE60'],
        'DESIGN' => ['fa-ruler-combined', '#8E44AD'],
        'PRODUCT' => ['fa-industry', '#E74C3C'],
    ];
    foreach ($keywords as $needle => $meta) {
        if (strpos($name, $needle) !== false) {
            return $meta;
        }
    }

    // Stable unique color from name hash (never all same blue building)
    $palette = ['#5B6CFF', '#008094', '#2ECC71', '#F39C12', '#9B59B6', '#1ABC9C', '#3498DB', '#E67E22', '#16A085', '#8E44AD', '#E74C3C', '#2980B9'];
    $color = $palette[abs(crc32($name)) % count($palette)];
    return ['fa-building-user', $color];
}

/**
 * Rewrite department icons to catalog (FA6-safe). Returns updated row count.
 */
function syncDepartmentIcons($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    // Fix known outdated FA5 class names still stored in DB
    $renames = [
        'fa-hand-holding-usd' => 'fa-hand-holding-dollar',
        'fa-shopping-cart' => 'fa-cart-shopping',
        'fa-pencil-ruler' => 'fa-ruler-combined',
    ];
    foreach ($renames as $old => $new) {
        $oldEsc = $conn->real_escape_string($old);
        $newEsc = $conn->real_escape_string($new);
        $conn->query("UPDATE departments SET icon_class = '{$newEsc}' WHERE icon_class = '{$oldEsc}'");
    }

    $updated = 0;
    $res = $conn->query('SELECT id, department_name, icon_class, icon_color FROM departments WHERE status = 1');
    if ($res) {
        $stmt = $conn->prepare('UPDATE departments SET icon_class = ?, icon_color = ? WHERE id = ?');
        while ($row = $res->fetch_assoc()) {
            [$icon, $color] = resolveDepartmentIcon($row['department_name']);
            $curIcon = (string) ($row['icon_class'] ?? '');
            $curColor = (string) ($row['icon_color'] ?? '');
            if ($curIcon !== $icon || $curColor !== $color) {
                $id = (int) $row['id'];
                $stmt->bind_param('ssi', $icon, $color, $id);
                $stmt->execute();
                $updated++;
            }
        }
        $stmt->close();
    }

    if ($closeAfter) {
        $conn->close();
    }
    return $updated;
}

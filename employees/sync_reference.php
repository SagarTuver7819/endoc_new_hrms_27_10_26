<?php
/**
 * Legacy Ocean / Reference HRMS sync — removed from Endoc HRMS UI.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Location: ' . app_url('employees/index.php'));
exit;

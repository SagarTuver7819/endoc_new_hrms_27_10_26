<?php
/**
 * Jobwork Entry removed from Endoc HRMS UI.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Location: ' . app_url('dashboard.php'));
exit;

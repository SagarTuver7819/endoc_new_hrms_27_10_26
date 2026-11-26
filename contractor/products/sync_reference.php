<?php
/**
 * Legacy Ocean / Reference Product sync — removed from Endoc HRMS UI.
 */
require_once __DIR__ . '/../_bootstrap.php';
requireLogin();
header('Location: ' . app_url('contractor/products/index.php'));
exit;

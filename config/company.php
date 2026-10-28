<?php
/**
 * Endoc Biotech Pvt Ltd company defaults
 * Change brand text here — used across app where needed.
 */

if (!defined('COMPANY_CODE')) {
    define('COMPANY_CODE', 'ENDOC_BIOTECH');
    define('COMPANY_DEFAULT_NAME', 'Endoc Biotech Pvt Ltd');
    define('COMPANY_INDUSTRY', 'Biotechnology');
    define('COMPANY_BRAND_COLOR', '#008094');
    define('COMPANY_ACCENT_COLOR', '#8B3A36');
    define('COMPANY_TAGLINE', 'HRMS · Department · Workers · Staff');

    // Common worker categories for manufacturing (use in future forms)
    define('EMPLOYEE_CATEGORIES', 'Staff,Worker,Contract,Trainee,Apprentice');
}

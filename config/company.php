<?php
/**
 * Fishfa Rubbers Limited company defaults (Reclaim Rubber / rCB manufacturing HRMS)
 * Change brand text here — used across app where needed.
 */

if (!defined('COMPANY_CODE')) {
    define('COMPANY_CODE', 'FISHFA_RUBBERS');
    define('COMPANY_DEFAULT_NAME', 'Fishfa Rubbers Limited');
    define('COMPANY_INDUSTRY', 'Reclaim Rubber Manufacturing');
    define('COMPANY_BRAND_COLOR', '#0c54a0');
    define('COMPANY_TAGLINE', 'Plant HRMS · Department · Workers · Staff');

    // Common worker categories for manufacturing (use in future forms)
    define('EMPLOYEE_CATEGORIES', 'Staff,Worker,Contract,Trainee,Apprentice');
}

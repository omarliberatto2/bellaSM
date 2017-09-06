<?php

// Files that are needed that aren't classes, as well as basic initialization
// Core requirements
define('IN_SCRIPT', 1);
define('HESK_PATH', '../');
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/bootstrap.php');
require_once(__DIR__ . '/../hesk_settings.inc.php');
require_once(__DIR__ . '/../inc/common.inc.php');
require_once(__DIR__ . '/Core/output.php');
require_once(__DIR__ . '/../hesk_settings.inc.php');
require_once(__DIR__ . '/http_response_code.php');
require_once(__DIR__ . '/../inc/admin_functions.inc.php');

hesk_load_api_database_functions();

global $hesk_settings;

// HESK files that require database access
require_once(__DIR__ . '/../inc/custom_fields.inc.php');

// Load the ApplicationContext
$applicationContext = new \ApplicationContext();
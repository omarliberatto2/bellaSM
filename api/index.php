<?php
// Properly handle error logging, as well as a fatal error workaround
require_once(__DIR__ . '/autoload.php');
error_reporting(0);
set_error_handler('errorHandler');
set_exception_handler('exceptionHandler');
register_shutdown_function('fatalErrorShutdownHandler');

$userContext = null;

function handle404() {
    print output(array(
        'message' => "The endpoint '{$_SERVER['REQUEST_URI']}' was not found. Double-check your request and submit again.",
        'uri' => $_SERVER['REQUEST_URI']
    ), 404);
}

function globalBefore() {
    if (defined('HESK_DEMO') && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        print_error('Demo Mode', 'Only read-only commands are available in demo mode!', null, 401);
        die();
    }
}

function internalHandler() {
    buildUserContextFromSession();
}

function authTokenHandler() {
    assertApiIsEnabled();
    $token = \BusinessLogic\Helpers::getHeader('X-AUTH-TOKEN');
    buildUserContext($token);
}

function internalOrAuthHandler() {
    $internalUse = \BusinessLogic\Helpers::getHeader('X-INTERNAL-CALL');

    if ($internalUse === 'true') {
        internalHandler();
    } else {
        authTokenHandler();
    }
}

function publicHandler() {
    //-- No-op
}

function assertApiIsEnabled() {
    global $applicationContext, $hesk_settings;

    /* @var $apiChecker \BusinessLogic\Settings\ApiChecker */
    $apiChecker = $applicationContext->get[\BusinessLogic\Settings\ApiChecker::class];

    if (!$apiChecker->isApiEnabled($hesk_settings)) {
        print output(array('message' => 'API Disabled'), 404);
        die();
    }

    return;
}

function buildUserContextFromSession() {
    global $userContext;

    hesk_session_start();

    if (empty($_SESSION['id'])) {
        throw new \BusinessLogic\Exceptions\SessionNotActiveException();
    }

    /* @var $userContext \BusinessLogic\Security\UserContext */
    $userContext = \BusinessLogic\Security\UserContext::fromDataRow($_SESSION);
}

function buildUserContext($xAuthToken) {
    global $applicationContext, $userContext, $hesk_settings;

    /* @var $userContextBuilder \BusinessLogic\Security\UserContextBuilder */
    $userContextBuilder = $applicationContext->get[\BusinessLogic\Security\UserContextBuilder::class];

    $userContext = $userContextBuilder->buildUserContext($xAuthToken, $hesk_settings);
}

function errorHandler($errorNumber, $errorMessage, $errorFile, $errorLine) {
    exceptionHandler(new Exception(sprintf("%s:%d\n\n%s", $errorFile, $errorLine, $errorMessage)));
}

/**
 * @param $exception Exception
 */
function exceptionHandler($exception) {
    global $applicationContext, $userContext, $hesk_settings;

    if (strpos($exception->getTraceAsString(), 'LoggingGateway') !== false) {
        //-- Suppress these for now, as it would cause issues to output two JSONs at one time.
        return;
    }


    /* @var $loggingGateway \DataAccess\Logging\LoggingGateway */
    $loggingGateway = $applicationContext->get[\DataAccess\Logging\LoggingGateway::class];

    // We don't cast API Friendly Exceptions as they're user-generated errors
    if (exceptionIsOfType($exception, \BusinessLogic\Exceptions\ApiFriendlyException::class)) {
        /* @var $castedException \BusinessLogic\Exceptions\ApiFriendlyException */
        $castedException = $exception;

        print_error($castedException->title, $castedException->getMessage(), $castedException->httpResponseCode);
    } elseif (exceptionIsOfType($exception, \Core\Exceptions\SQLException::class)) {
        /* @var $castedException \Core\Exceptions\SQLException */
        $castedException = $exception;

        $logId = tryToLog(getLoggingLocation($exception),
                "Fought an uncaught SQL exception: " . $castedException->failingQuery, $castedException->getTraceAsString(),
                $userContext, $hesk_settings);

        $logIdText = $logId === null ? "Additionally, the error could not be logged! :'(" : "Log ID: {$logId}";
        print_error("SQL Exception", "Fought an uncaught SQL exception. Check the logs for more information. {$logIdText}", $logId);
    } else {
        $logId = tryToLog(getLoggingLocation($exception),
            $exception->getMessage(), $exception->getTraceAsString(),
            $userContext, $hesk_settings);

        $logIdText = $logId === null ? "Additionally, the error could not be logged! :'(" : "Log ID: {$logId}";
        print_error("Exception Occurred", "Fought an uncaught exception. Check the logs for more information. {$logIdText}", $logId);
    }

    die();
}

/**
 * @param $location string
 * @param $message string
 * @param $stackTrace string
 * @param $userContext \BusinessLogic\Security\UserContext
 * @param $heskSettings array
 * @return int|null The inserted ID, or null if failed to log
 * @internal param Exception $exception
 */
function tryToLog($location, $message, $stackTrace, $userContext, $heskSettings) {
    global $applicationContext;

    /* @var $loggingGateway \DataAccess\Logging\LoggingGateway */
    $loggingGateway = $applicationContext->get[\DataAccess\Logging\LoggingGateway::class];

    try {
        return $loggingGateway->logError($location, $message, $stackTrace, $userContext, $heskSettings);
    } catch (Exception $squished) {
        return null;
    }
}

/**
 * @param $exception Exception
 * @return string The location of the exception
 */
function getLoggingLocation($exception) {
    // http://stackoverflow.com/a/9133897/1509431
    $trace = $exception->getTrace();
    $lastCall = $trace[0];
    $location = basename($lastCall['file'], '.php');
    if ($location === null || trim($location) === '') {
        $location = 'N/A';
    }
    return "REST API: {$location}";
}

/**
 * @param $exception Exception thrown exception
 * @param $class string The name of the expected exception type
 * @return bool
 */
function exceptionIsOfType($exception, $class) {
    return is_a($exception, $class);
}

function fatalErrorShutdownHandler() {
    $last_error = error_get_last();
    if ($last_error['type'] === E_ERROR) {
        // fatal error
        errorHandler(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);
    }
}

Link::before('globalBefore');

Link::all(array(
    // Categories
    '/v1/categories' => action(\Controllers\Categories\CategoryController::class . '::printAllCategories'),
    '/v1/categories/{i}' => action(\Controllers\Categories\CategoryController::class),
    // Tickets
    '/v1/tickets' => action(\Controllers\Tickets\CustomerTicketController::class),
    // Tickets - Staff
    '/v1/staff/tickets/{i}' => action(\Controllers\Tickets\StaffTicketController::class),
    // Attachments
    '/v1/tickets/{a}/attachments/{i}' => action(\Controllers\Attachments\PublicAttachmentController::class . '::getRaw'),
    '/v1/staff/tickets/{i}/attachments' => action(\Controllers\Attachments\StaffTicketAttachmentsController::class),
    '/v1/staff/tickets/{i}/attachments/{i}' => action(\Controllers\Attachments\StaffTicketAttachmentsController::class),
    // Statuses
    '/v1/statuses' => action(\Controllers\Statuses\StatusController::class),
    // Settings
    '/v1/settings' => action(\Controllers\Settings\SettingsController::class),

    /* Internal use only routes */
    // Resend email response
    '/v1-internal/staff/tickets/{i}/resend-email' =>
        action(\Controllers\Tickets\ResendTicketEmailToCustomerController::class, SecurityHandler::INTERNAL),
    // Custom Navigation
    '/v1-internal/custom-navigation/all' =>
        action(\Controllers\Navigation\CustomNavElementController::class . '::getAll', SecurityHandler::INTERNAL),
    '/v1-internal/custom-navigation' =>
        action(\Controllers\Navigation\CustomNavElementController::class, SecurityHandler::INTERNAL),
    '/v1-internal/custom-navigation/{i}' =>
        action(\Controllers\Navigation\CustomNavElementController::class, SecurityHandler::INTERNAL),
    '/v1-internal/custom-navigation/{i}/sort/{s}' =>
        action(\Controllers\Navigation\CustomNavElementController::class . '::sort', SecurityHandler::INTERNAL),

    '/v1-public/hesk-version' =>
        action(\Controllers\System\HeskVersionController::class . '::getHeskVersion', SecurityHandler::OPEN),
    '/v1-public/mods-for-hesk-version' =>
        action(\Controllers\System\HeskVersionController::class . '::getModsForHeskVersion', SecurityHandler::OPEN),

    // Any URL that doesn't match goes to the 404 handler
    '404' => 'handle404'
));

function action($class, $securityHandler = SecurityHandler::AUTH_TOKEN) {
    return [$class, $class, $securityHandler];
}

class SecurityHandler {
    const OPEN = 'publicHandler';
    const INTERNAL = 'internalHandler';
    const AUTH_TOKEN = 'authTokenHandler';
    const INTERNAL_OR_AUTH_TOKEN = 'internalOrAuthHandler';
}
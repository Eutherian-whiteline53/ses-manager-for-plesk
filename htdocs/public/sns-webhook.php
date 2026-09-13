<?php

$pleskBootstrapCandidates = array(
    dirname(__FILE__) . '/../../../../plib/api-common/cu.php',
    '/usr/local/psa/admin/plib/api-common/cu.php',
    '/opt/psa/admin/plib/api-common/cu.php',
);

$pleskBootstrapLoaded = false;
foreach ($pleskBootstrapCandidates as $bootstrapPath) {
    if (is_file($bootstrapPath)) {
        require_once $bootstrapPath;
        $pleskBootstrapLoaded = true;
        break;
    }
}

if (!$pleskBootstrapLoaded) {
    http_response_code(500);
    echo 'Plesk bootstrap not found';
    exit;
}

$autoloadCandidates = array(
    dirname(__FILE__) . '/../../vendor/autoload.php',
    dirname(__FILE__) . '/../../../../plib/modules/ses-manager/vendor/autoload.php',
    '/usr/local/psa/admin/plib/modules/ses-manager/vendor/autoload.php',
    '/opt/psa/admin/plib/modules/ses-manager/vendor/autoload.php',
);

$autoloadLoaded = false;
foreach ($autoloadCandidates as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        $autoloadLoaded = true;
        break;
    }
}

if (!$autoloadLoaded) {
    http_response_code(500);
    echo 'Extension autoload not found';
    exit;
}

pm_Bootstrap::init();

use Library\Service\WebhookService;
use Library\Repository\SnsWebhookAttemptRepository;

header('Content-Type: text/plain');

function ses_manager_public_log($level, $message)
{
    error_log($message);
}

$payload = file_get_contents('php://input');

if ($payload === false || $payload === '' || strlen($payload) > WebhookService::MAX_PAYLOAD_BYTES) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$webhookService = new WebhookService();
$attemptRepository = new SnsWebhookAttemptRepository();

if (!$webhookService->verifySnsSignature($payload)) {
    $reason = $webhookService->getLastVerificationError() ?: 'SNS webhook signature verification failed.';
    $attemptRepository->createFromPayload($payload, 'failed', $reason, 403);
    ses_manager_public_log('error', 'SES Manager: ' . $reason);
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$result = $webhookService->handleSnsPayload($payload);

if (!$result->success) {
    $attemptRepository->createFromPayload($payload, 'failed', $result->message, 400);
    ses_manager_public_log('warning', 'SES Manager: SNS webhook processing failed: ' . $result->message);
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$attemptRepository->createFromPayload($payload, 'success', $result->message, 200);

http_response_code(200);
echo 'OK';

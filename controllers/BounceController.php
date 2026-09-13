<?php

use Library\Repository\DomainRepository;
use Library\Repository\EventRepository;
use Library\Repository\SnsConfirmationRepository;
use Library\Repository\SnsWebhookAttemptRepository;
use Library\Service\FeedbackMetricsService;
use Library\Service\LicenseService;
use Library\Service\ReputationService;
use Library\Service\SnsSetupService;

class BounceController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('bouncePageTitle');
    }

    public function indexAction()
    {
        if (!$this->_requireFeature(LicenseService::FEATURE_EVENT_VIEWER, 'index', 'bounce')) {
            return;
        }

        $eventRepository = new EventRepository();
        $events = $eventRepository->findRecent(100);

        $this->view->events = $events;
        $this->view->domainNames = $this->_domainNameMap();
        $this->view->snsConfirmations = (new SnsConfirmationRepository())->findRecent(10);
        $this->view->snsWebhookAttempts = (new SnsWebhookAttemptRepository())->findRecent(20);
        $this->view->webhookUrl = $this->_webhookUrl();
        $reputation = (new ReputationService())->getSnapshot(false);
        $this->view->reputationSnapshot = $reputation['snapshot'];
        $this->view->feedbackMetrics = (new LicenseService())->canUseFeature(LicenseService::FEATURE_FEEDBACK_METRICS)
            ? (new FeedbackMetricsService())->getMetrics()
            : array('success' => false, 'message' => pm_Locale::lmsg('featureRequiresUpgradeMessage'), 'rows' => array());
    }

    public function exportAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        if (!$this->_requireFeature(LicenseService::FEATURE_CSV_EXPORT, 'index', 'bounce')) {
            return;
        }

        $eventRepository = new EventRepository();
        $events = $eventRepository->findRecent(1000);
        $domainNames = $this->_domainNameMap();

        $this->getResponse()
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="ses-bounce-events.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array(
            pm_Locale::lmsg('domainColumn'),
            pm_Locale::lmsg('eventTypeColumn'),
            pm_Locale::lmsg('bounceTypeColumn'),
            pm_Locale::lmsg('recipientColumn'),
            pm_Locale::lmsg('messageIdColumn'),
            pm_Locale::lmsg('receivedAtColumn'),
        ));

        foreach ($events as $event) {
            $domainName = isset($domainNames[$event['domain_id']]) ? $domainNames[$event['domain_id']] : '';

            fputcsv($output, array(
                $domainName,
                $event['event_type'],
                $event['bounce_type'],
                $event['recipient'],
                $event['message_id'],
                $event['received_at'],
            ));
        }

        fclose($output);
    }

    public function configureSnsAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_requirePost('index', 'bounce')) {
            return;
        }
        if (!$this->_requireFeature(LicenseService::FEATURE_SNS_AUTOMATION, 'index', 'bounce')) {
            return;
        }

        $result = (new SnsSetupService())->configureAll($this->_webhookUrl());
        $this->_status->addMessage($result['success'] ? 'info' : 'warning', $result['message']);
        $this->_redirectTo('index', 'bounce');
    }

    public function disableRawAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_requirePost('index', 'bounce')) {
            return;
        }
        if (!$this->_requireFeature(LicenseService::FEATURE_SNS_AUTOMATION, 'index', 'bounce')) {
            return;
        }

        $result = (new SnsSetupService())->disableRawMessageDelivery($this->_webhookUrl());
        $this->_status->addMessage($result['success'] ? 'info' : 'warning', $result['message']);
        $this->_redirectTo('index', 'bounce');
    }

    private function _domainNameMap()
    {
        $map = array();
        foreach ((new DomainRepository())->findAll() as $domain) {
            $map[$domain['id']] = $domain['domain_name'];
        }

        return $map;
    }

    private function _webhookUrl()
    {
        return $this->_absoluteExtensionUrl('public/sns-webhook.php');
    }
}

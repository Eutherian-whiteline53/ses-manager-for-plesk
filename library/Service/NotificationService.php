<?php

namespace Library\Service;

use Library\Repository\DomainRepository;

/**
 * Raises a Plesk panel notification for domains with a critical
 * deliverability score when the host Plesk build exposes pm_Notification.
 */
class NotificationService
{
    const CRITICAL_SCORE_THRESHOLD = 50;
    const NOTIFICATION_ID = 'ses-manager-critical-domains';

    /** @var DomainRepository */
    private $domainRepository;

    public function __construct(?DomainRepository $domainRepository = null)
    {
        $this->domainRepository = $domainRepository ?: new DomainRepository();
    }

    public function notifyCriticalDomains()
    {
        $critical = array();

        foreach ($this->domainRepository->findAll() as $domain) {
            if ((int) $domain['score'] < self::CRITICAL_SCORE_THRESHOLD) {
                $critical[] = $domain['domain_name'];
            }
        }

        if (empty($critical)) {
            if (method_exists('pm_Notification', 'cancelAll')) {
                \pm_Notification::cancelAll(self::NOTIFICATION_ID);
            }
            return;
        }

        if (!method_exists('pm_Notification', 'create')) {
            return;
        }

        $message = \pm_Locale::lmsg('notificationCriticalDomainsMessage', array('domains' => implode(', ', $critical)));
        \pm_Notification::create(self::NOTIFICATION_ID, $message);
    }
}

<?php

namespace Library\Controller;

use Library\Service\LicenseService;

abstract class ActionController extends \pm_Controller_Action
{
    public function init()
    {
        parent::init();

        $groups = array(
            array(
                'label' => \pm_Locale::lmsg('navGroupMain'),
                'items' => array(
                    'index' => 'navDashboard',
                    'domains' => 'navDomains',
                    'reputation' => 'navReputation',
                    'dns-health' => 'navDnsHealth',
                ),
            ),
            array(
                'label' => \pm_Locale::lmsg('navGroupTools'),
                'items' => array(
                    'smarthost' => 'navSmarthost',
                    'mail-test' => 'navMailTest',
                    'bounce' => 'navBounce',
                    'bulk-verify' => 'navBulkVerify',
                    'cloudflare' => 'navCloudflare',
                ),
            ),
            array(
                'label' => \pm_Locale::lmsg('navGroupAdmin'),
                'items' => array(
                    'setup' => 'navSetup',
                    'settings' => 'navSettings',
                    'advanced-settings' => 'navAdvancedSettings',
                ),
            ),
        );

        $activeController = $this->getRequest()->getControllerName();
        foreach ($groups as &$group) {
            $items = array();
            foreach ($group['items'] as $controllerName => $labelKey) {
                $items[] = array(
                    'label' => \pm_Locale::lmsg($labelKey),
                    'url' => $this->_helper->url('index', $controllerName),
                    'active' => $activeController === $controllerName,
                );
            }
            $group['items'] = $items;
            $group['active'] = false;
            foreach ($items as $item) {
                if ($item['active']) {
                    $group['active'] = true;
                    break;
                }
            }
        }
        unset($group);

        $flatItems = array();
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $flatItems[] = $item;
            }
        }

        $this->view->navGroups = $groups;
        $this->view->navItems = $flatItems;
        $this->view->csrfField = $this->_csrfField();
    }

    protected function _getNavigationItems()
    {
        return array(
            'index' => 'navDashboard',
            'domains' => 'navDomains',
            'reputation' => 'navReputation',
            'dns-health' => 'navDnsHealth',
            'smarthost' => 'navSmarthost',
            'mail-test' => 'navMailTest',
            'bounce' => 'navBounce',
            'bulk-verify' => 'navBulkVerify',
            'cloudflare' => 'navCloudflare',
            'setup' => 'navSetup',
            'settings' => 'navSettings',
            'advanced-settings' => 'navAdvancedSettings',
        );
    }

    protected function _setFlatNavigationItems()
    {
        $items = array();
        foreach ($this->_getNavigationItems() as $controllerName => $labelKey) {
            $items[] = array(
                'label' => \pm_Locale::lmsg($labelKey),
                'url' => $this->_helper->url('index', $controllerName),
                'active' => $this->getRequest()->getControllerName() === $controllerName,
            );
        }

        $this->view->navItems = $items;
    }

    /**
     * Redirect to another action. AJAX form submissions (Jsw.FormAjax, sent
     * with X-Requested-With: XMLHttpRequest) expect a JSON response with a
     * "redirect" key - returning a 302 to them makes the browser follow the
     * redirect and dump the resulting HTML/JSON as plain text. Plain <form>
     * submissions are not XHR, so they need a real HTTP redirect instead.
     */
    protected function _redirectTo($action, $controller = null, array $params = array())
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
            $url = $this->_helper->url($action, $controller, null, empty($params) ? null : $params);

            $this->_helper->redirector->jsonStatusResponse(array(
                'redirect' => $url,
            ));

            return;
        }

        $this->_helper->redirector($action, $controller, null, $params);
    }

    protected function _requirePost($redirectAction = 'index', $redirectController = null, array $params = array())
    {
        if ($this->getRequest()->isPost() && $this->_validateCsrfToken()) {
            return true;
        }

        if ($this->getRequest()->isPost()) {
            $this->_status->addMessage('error', \pm_Locale::lmsg('csrfValidationFailed'));
        }

        $this->_redirectTo($redirectAction, $redirectController, $params);

        return false;
    }

    protected function _validateCsrfToken()
    {
        $submitted = (string) $this->_getParam('ses_manager_csrf', '');

        return $submitted !== '' && hash_equals($this->_csrfToken(), $submitted);
    }

    protected function _csrfField()
    {
        return '<input type="hidden" name="ses_manager_csrf" value="' . htmlspecialchars($this->_csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    private function _csrfToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['ses_manager_csrf']) || !is_string($_SESSION['ses_manager_csrf'])) {
            $_SESSION['ses_manager_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['ses_manager_csrf'];
    }

    protected function _requireFeature($featureKey, $redirectAction = 'index', $redirectController = null, array $params = array())
    {
        $licenseService = new LicenseService();
        if ($licenseService->canUseFeature($featureKey)) {
            return true;
        }

        $requiredPlan = $licenseService->requiredPlanForFeature($featureKey);
        $message = \pm_Locale::lmsg('featureRequiresUpgradeMessage');
        if ($requiredPlan) {
            $message .= ' ' . sprintf(\pm_Locale::lmsg('featureRequiredPlanMessage'), ucwords(str_replace('_', ' ', $requiredPlan)));
        }

        $this->_status->addMessage('warning', $message);
        $this->_redirectTo($redirectAction, $redirectController, $params);

        return false;
    }

    protected function _absoluteExtensionUrl($relativePath)
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        if (!preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
            $host = isset($_SERVER['SERVER_NAME']) ? (string) $_SERVER['SERVER_NAME'] : '';
        }
        if (!preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
            $host = 'localhost';
        }

        return $scheme . $host . \pm_Context::getBaseUrl() . ltrim($relativePath, '/');
    }
}

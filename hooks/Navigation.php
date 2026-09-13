<?php

class Modules_SesManager_Hook_Navigation extends pm_Hook_Navigation
{
    public function getNavigation()
    {
        return new pm_Navigation_Page(array(
            'label' => pm_Locale::lmsg('navTitle'),
            'id' => 'ses-manager',
            'controller' => 'index',
            'action' => 'index',
            'pages' => array(
                array('id' => 'index', 'label' => pm_Locale::lmsg('navDashboard'), 'controller' => 'index', 'action' => 'index'),
                array('id' => 'domains', 'label' => pm_Locale::lmsg('navDomains'), 'controller' => 'domains', 'action' => 'index'),
                array('id' => 'reputation', 'label' => pm_Locale::lmsg('navReputation'), 'controller' => 'reputation', 'action' => 'index'),
                array('id' => 'dns-health', 'label' => pm_Locale::lmsg('navDnsHealth'), 'controller' => 'dns-health', 'action' => 'index'),
                array('id' => 'smarthost', 'label' => pm_Locale::lmsg('navSmarthost'), 'controller' => 'smarthost', 'action' => 'index'),
                array('id' => 'mail-test', 'label' => pm_Locale::lmsg('navMailTest'), 'controller' => 'mail-test', 'action' => 'index'),
                array('id' => 'bounce', 'label' => pm_Locale::lmsg('navBounce'), 'controller' => 'bounce', 'action' => 'index'),
                array('id' => 'bulk-verify', 'label' => pm_Locale::lmsg('navBulkVerify'), 'controller' => 'bulk-verify', 'action' => 'index'),
                array('id' => 'cloudflare', 'label' => pm_Locale::lmsg('navCloudflare'), 'controller' => 'cloudflare', 'action' => 'index'),
                array('id' => 'setup', 'label' => pm_Locale::lmsg('navSetup'), 'controller' => 'setup', 'action' => 'index'),
                array('id' => 'settings', 'label' => pm_Locale::lmsg('navSettings'), 'controller' => 'settings', 'action' => 'index'),
                array('id' => 'advanced-settings', 'label' => pm_Locale::lmsg('navAdvancedSettings'), 'controller' => 'advanced-settings', 'action' => 'index'),
            ),
        ));
    }
}

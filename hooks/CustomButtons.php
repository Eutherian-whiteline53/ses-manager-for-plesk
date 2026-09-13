<?php

class Modules_SesManager_CustomButtons extends pm_Hook_CustomButtons
{
    public function getButtons()
    {
        $navigationIcon = pm_Context::getBaseUrl() . 'images/icon.svg';

        $buttons = array(
            array(
                'place' => self::PLACE_ADMIN_NAVIGATION,
                'section' => self::SECTION_NAV_SERVER_MANAGEMENT,
                'title' => pm_Locale::lmsg('navTitle'),
                'description' => pm_Locale::lmsg('navDescription'),
                'icon' => $navigationIcon,
                'link' => pm_Context::getActionUrl('index', 'index'),
            ),
        );

        if (defined(__CLASS__ . '::PLACE_DOMAIN_PROPERTIES')) {
            $buttons[] = array(
                'place' => self::PLACE_DOMAIN_PROPERTIES,
                'title' => pm_Locale::lmsg('sesDomainButtonTitle'),
                'description' => pm_Locale::lmsg('sesDomainButtonDescription'),
                'icon' => $navigationIcon,
                'link' => pm_Context::getActionUrl('domains', 'open'),
                'contextParams' => true,
                'visibility' => array($this, 'isAdminVisible'),
            );
        }

        if (defined(__CLASS__ . '::PLACE_DOMAIN')) {
            $buttons[] = array(
                'place' => self::PLACE_DOMAIN,
                'title' => pm_Locale::lmsg('sesDomainButtonTitle'),
                'description' => pm_Locale::lmsg('sesDomainButtonDescription'),
                'icon' => $navigationIcon,
                'link' => pm_Context::getActionUrl('domains', 'open'),
                'contextParams' => true,
                'visibility' => array($this, 'isAdminVisible'),
            );
        }

        if (defined(__CLASS__ . '::PLACE_HOSTING_PANEL_NAVIGATION')) {
            $buttons[] = array(
                'place' => self::PLACE_HOSTING_PANEL_NAVIGATION,
                'title' => pm_Locale::lmsg('sesMailButtonTitle'),
                'description' => pm_Locale::lmsg('sesDomainButtonDescription'),
                'icon' => $navigationIcon,
                'link' => pm_Context::getActionUrl('domains', 'open'),
                'contextParams' => true,
                'visibility' => array($this, 'isAdminVisible'),
            );
        }

        return $buttons;
    }

    public function isAdminVisible()
    {
        try {
            return method_exists('pm_Session', 'getClient') ? pm_Session::getClient()->isAdmin() : true;
        } catch (Exception $e) {
            return false;
        }
    }
}

<?php

namespace Library\Form;

class CloudflareForm extends \pm_Form_Simple
{
    use CsrfTrait;

    public function init()
    {
        $this->addSesManagerCsrfElement();

        $this->addElement('password', 'cloudflare_token', array(
            'label' => $this->lmsg('cloudflareTokenLabel'),
            'description' => $this->lmsg('leaveBlankToKeep'),
            'renderPassword' => true,
        ));

        $this->addElement('text', 'cloudflare_account_id', array(
            'label' => $this->lmsg('cloudflareAccountIdLabel'),
            'description' => $this->lmsg('cloudflareAccountIdHint'),
            'validators' => array(
                array('Regex', false, array('/^$|^[a-f0-9]{32}$/i')),
            ),
        ));

        $this->addElement('select', 'cloudflare_sync_mode', array(
            'label' => $this->lmsg('cloudflareSyncModeLabel'),
            'description' => $this->lmsg('cloudflareSyncModeHint'),
            'multiOptions' => array(
                'ses' => $this->lmsg('cloudflareSyncSesOnly'),
                'all' => $this->lmsg('cloudflareSyncAllRecords'),
            ),
        ));

        $this->addControlButtons(array(
            'sendTitle' => $this->lmsg('saveTokenButton'),
            'cancelHidden' => true,
        ));
    }
}

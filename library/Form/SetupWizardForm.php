<?php

namespace Library\Form;

class SetupWizardForm extends \pm_Form_Simple
{
    use CsrfTrait;

    public function init()
    {
        $this->addSesManagerCsrfElement();

        $this->addElement('text', 'region', array(
            'label' => $this->lmsg('regionLabel'),
            'description' => $this->lmsg('regionHint'),
            'required' => true,
            'filters' => array('StringTrim'),
            'validators' => array(
                array('regex', false, array('/^[a-z]{2}-[a-z]+-[0-9]$/')),
            ),
        ));

        $this->addElement('text', 'aws_access_key', array(
            'label' => $this->lmsg('awsAccessKeyLabel'),
            'description' => $this->lmsg('leaveBlankToKeep'),
            'filters' => array('StringTrim'),
        ));

        $this->addElement('password', 'aws_secret_key', array(
            'label' => $this->lmsg('awsSecretKeyLabel'),
            'description' => $this->lmsg('leaveBlankToKeep'),
        ));

        $this->addElement('text', 'smtp_username', array(
            'label' => $this->lmsg('smtpUsernameLabel'),
            'description' => $this->lmsg('leaveBlankToKeep'),
            'filters' => array('StringTrim'),
        ));

        $this->addElement('password', 'smtp_password', array(
            'label' => $this->lmsg('smtpPasswordLabel'),
            'description' => $this->lmsg('leaveBlankToKeep'),
        ));

        $this->addElement('text', 'mail_from_subdomain', array(
            'label' => $this->lmsg('mailFromSubdomainLabel'),
            'description' => $this->lmsg('mailFromSubdomainHint'),
            'validators' => array(
                array('Regex', false, array('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i')),
            ),
        ));

        $this->addElement('checkbox', 'auto_ses_on_create', array(
            'label' => $this->lmsg('autoSesOnCreateLabel'),
            'description' => $this->lmsg('autoSesOnCreateHint'),
        ));

        $this->addElement('text', 'sns_topic_arn', array(
            'label' => $this->lmsg('snsTopicArnLabel'),
            'description' => $this->lmsg('snsTopicArnHint'),
            'validators' => array(
                array('Regex', false, array('/^$|^arn:aws:sns:[a-z0-9-]+:\d{12}:[A-Za-z0-9_.-]+$/')),
            ),
        ));

        $this->addElement('password', 'cloudflare_token', array(
            'label' => $this->lmsg('cloudflareTokenLabel'),
            'description' => $this->lmsg('setupCloudflareTokenHint'),
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

        $this->addElement('select', 'default_dmarc_policy', array(
            'label' => $this->lmsg('dmarcPolicyLabel'),
            'multiOptions' => array(
                'none' => 'none',
                'quarantine' => 'quarantine',
                'reject' => 'reject',
            ),
        ));

        $this->addControlButtons(array(
            'sendTitle' => $this->lmsg('completeSetupButton'),
            'cancelHidden' => true,
        ));
    }
}

<?php

namespace Library\Form;

class SettingsForm extends \pm_Form_Simple
{
    use CsrfTrait;

    public function init()
    {
        $this->addSesManagerCsrfElement();

        $this->addElement('select', 'default_spf_policy', array(
            'label' => $this->lmsg('spfPolicyLabel'),
            'multiOptions' => array(
                '~all' => '~all (softfail)',
                '-all' => '-all (fail)',
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

        $this->addElement('checkbox', 'smarthost_enabled', array(
            'label' => $this->lmsg('smarthostEnabledLabel'),
        ));

        $this->addElement('text', 'sns_topic_arn', array(
            'label' => $this->lmsg('snsTopicArnLabel'),
            'description' => $this->lmsg('snsTopicArnHint'),
            'validators' => array(
                array('Regex', false, array('/^$|^arn:aws:sns:[a-z0-9-]+:\d{12}:[A-Za-z0-9_.-]+$/')),
            ),
        ));

        $this->addElement('text', 'cache_ttl_seconds', array(
            'label' => $this->lmsg('cacheTtlLabel'),
            'validators' => array(array('Int', false), array('GreaterThan', false, array(0))),
        ));

        $this->addElement('text', 'retention_days', array(
            'label' => $this->lmsg('retentionDaysLabel'),
            'validators' => array(array('Int', false), array('GreaterThan', false, array(0))),
        ));

        $this->addControlButtons(array(
            'sendTitle' => $this->lmsg('saveButton'),
            'cancelHidden' => true,
        ));
    }
}

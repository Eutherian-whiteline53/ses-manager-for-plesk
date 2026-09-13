<?php

namespace Library\Form;

class AdvancedSettingsForm extends \pm_Form_Simple
{
    use CsrfTrait;

    public function init()
    {
        $this->addSesManagerCsrfElement();

        foreach ($this->retentionFields() as $name => $label) {
            $this->addElement('text', $name, array(
                'label' => $this->lmsg($label),
                'description' => $this->lmsg('advancedRetentionDaysHint'),
                'validators' => array(
                    array('Int', false),
                    array('Between', false, array('min' => 1, 'max' => 3650)),
                ),
            ));
        }

        $this->addControlButtons(array(
            'sendTitle' => $this->lmsg('saveButton'),
            'cancelHidden' => true,
        ));
    }

    private function retentionFields()
    {
        return array(
            'mail_test_retention_days' => 'mailTestRetentionDaysLabel',
            'event_retention_days' => 'eventRetentionDaysLabel',
            'webhook_attempt_retention_days' => 'webhookAttemptRetentionDaysLabel',
            'job_retention_days' => 'jobRetentionDaysLabel',
            'reputation_retention_days' => 'reputationRetentionDaysLabel',
            'ip_reputation_retention_days' => 'ipReputationRetentionDaysLabel',
            'log_retention_days' => 'logRetentionDaysLabel',
        );
    }
}

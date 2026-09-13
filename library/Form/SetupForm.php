<?php

namespace Library\Form;

class SetupForm extends \pm_Form_Simple
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

        $this->addControlButtons(array(
            'sendTitle' => $this->lmsg('testConnectionButton'),
            'cancelHidden' => true,
        ));
    }
}

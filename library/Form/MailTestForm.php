<?php

namespace Library\Form;

class MailTestForm extends \pm_Form_Simple
{
    use CsrfTrait;

    public function init()
    {
        $this->addSesManagerCsrfElement();

        $this->addElement('text', 'from_email', array(
            'label' => $this->lmsg('fromEmailLabel'),
            'required' => true,
            'validators' => array('EmailAddress'),
        ));

        $this->addElement('text', 'to_email', array(
            'label' => $this->lmsg('toEmailLabel'),
            'required' => true,
            'validators' => array('EmailAddress'),
        ));

        $this->addElement('text', 'subject', array(
            'label' => $this->lmsg('subjectLabel'),
            'required' => true,
            'filters' => array('StringTrim'),
        ));

        $this->addElement('textarea', 'body', array(
            'label' => $this->lmsg('bodyLabel'),
            'required' => true,
            'rows' => 5,
        ));

        $this->addControlButtons(array(
            'sendTitle' => $this->lmsg('sendTestMailButton'),
            'cancelHidden' => true,
        ));
    }
}

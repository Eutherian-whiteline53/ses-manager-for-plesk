<?php

namespace Library\Form;

trait CsrfTrait
{
    protected function addSesManagerCsrfElement()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['ses_manager_csrf']) || !is_string($_SESSION['ses_manager_csrf'])) {
            $_SESSION['ses_manager_csrf'] = bin2hex(random_bytes(32));
        }

        $this->addElement('hidden', 'ses_manager_csrf', array(
            'value' => $_SESSION['ses_manager_csrf'],
            'decorators' => array('ViewHelper'),
        ));
    }
}

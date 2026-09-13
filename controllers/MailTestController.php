<?php

use Library\Form\MailTestForm;
use Library\Repository\MailTestRepository;
use Library\Repository\SettingsRepository;
use Library\Service\SmtpTestService;

class MailTestController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('mailTestPageTitle');
    }

    public function indexAction()
    {
        $form = new MailTestForm();

        if ($this->getRequest()->isPost()) {
            if (!$this->_validateCsrfToken()) {
                $this->_status->addMessage('error', pm_Locale::lmsg('csrfValidationFailed'));
                $this->_redirectTo('index');
                return;
            }

            if ($form->isValid($this->getRequest()->getPost())) {
                $this->_handleSubmit($form->getValues());
            }

            $this->_redirectTo('index');
            return;
        }

        $mailTestRepository = new MailTestRepository();

        $this->view->form = $form;
        $this->view->history = $mailTestRepository->findRecent();
    }

    private function _handleSubmit(array $values)
    {
        $settingsRepository = new SettingsRepository();
        $credentials = $settingsRepository->getSmtpCredentials();
        $settings = $settingsRepository->get();

        if ($credentials['username'] === null || $credentials['password'] === null) {
            $this->_status->addMessage('error', pm_Locale::lmsg('smtpCredentialsMissing'));
            return;
        }

        $host = sprintf('email-smtp.%s.amazonaws.com', $settings['default_region']);

        $smtpService = new SmtpTestService();
        $result = $smtpService->sendTestMail(
            $host,
            587,
            $credentials['username'],
            $credentials['password'],
            $values['from_email'],
            $values['to_email'],
            $values['subject'],
            $values['body']
        );

        $mailTestRepository = new MailTestRepository();
        $mailTestRepository->create(array(
            'domain_id' => null,
            'from_email' => $values['from_email'],
            'to_email' => $values['to_email'],
            'subject' => $values['subject'],
            'status' => $result->success ? 'success' : 'failed',
            'provider_message_id' => $result->providerMessageId,
            'response' => $result->message,
        ));

        $this->_status->addMessage($result->success ? 'info' : 'error', $result->message);
    }
}

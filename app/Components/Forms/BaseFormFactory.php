<?php

declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Forms\Form;
use Nette\Mail\Message;

abstract class BaseFormFactory
{
    public function __construct(
        protected readonly \Nette\Mail\Mailer $mailer,
        protected readonly string $adminEmail,
    ) {}

    protected function addCommonFields(Form $form): void
    {
        // Honeypot
        $form->addText('address')
            ->setHtmlAttribute('style', 'display:none')
            ->setOmitted();

        // nahradit manualnim checkem viz nais
//        // reCAPTCHA v3
//        $form->addReCaptcha('recaptcha')
//            ->setThreshold(0.5);
    }

    protected function sendMail(array $data, string $subject, string $template = null): void
    {
        $mail = new Message;
        $mail->setFrom($data['email'], $data['name'] ?? null)
            ->addTo($this->adminEmail)
            ->setSubject($subject);

        if ($template) {
            $mail->setHtmlBody($this->renderTemplate($template, $data));
        }

        $this->mailer->send($mail);
    }

    private function renderTemplate(string $template, array $data): string
    {
        // optional: use Latte for beautiful emails
        return "TODO: Latte template";
    }
}
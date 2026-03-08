<?php

declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Form;
use Nette\Mail\Message;

abstract class BaseFormFactory
{
    public function __construct(
        protected readonly \Nette\Mail\Mailer $mailer,
        protected readonly string $adminEmail,
    ) {}

    protected function addCommonFields(Form $form): void
    {
        $form->addText('address')
            ->setHtmlAttribute('style', 'display:none')
            ->setOmitted();
    }

    protected function sendMail(array $data, string $subject, string $template = null): void
    {
        // TODO: fix it for prod
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
        return "TODO: Latte template";
    }
}
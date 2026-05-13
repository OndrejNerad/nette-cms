<?php

declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Form;
use Nette\Http\IRequest;

final class ContactFormFactory extends BaseFormFactory
{
    public function __construct(
        \Nette\Mail\Mailer $mailer,
        string $adminEmail,
        private readonly string $recaptchaSiteKey,
        private readonly string $recaptchaSecretKey,
        private readonly IRequest $httpRequest,
    ) {
        parent::__construct($mailer, $adminEmail);
    }

    public function create(callable $onSuccess, string $action): Form
    {
        $form = new Form;
        $form->setAction($action);

        $form->addText('name', 'Jméno')
            ->setRequired('Zadejte prosím jméno.');

        $form->addText('email', 'E-mail')
            ->setRequired('Zadejte prosím e-mail.')
            ->addRule(Form::EMAIL, 'E-mail není platný.');

        $form->addText('phone', 'Telefon')
            ->setRequired('Zadejte prosím telefon.');
//            ->addRule(Form::PATTERN, 'Telefon není platný.', '^[+]?[0-9 \-()]{9,20}$');

        $form->addTextArea('message', 'Zpráva')
            ->setRequired('Napište prosím zprávu.');

        // reCAPTCHA — hidden field, value filled by JS
//        $form->addHidden('g-recaptcha-response')
//            ->setRequired(false); // validated manually in onSuccess

        $this->addCommonFields($form); // honeypot

        $form->addSubmit('send', 'Odeslat');

        $form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSuccess): void {
            // Honeypot check — bots fill hidden address field
            if (!empty($data->address)) {
                // Silent kill — bot, pretend success
                $onSuccess();
                return;
            }

            // reCAPTCHA verification
            $recaptchaResponse = $this->httpRequest->getPost('recaptcha_token');
            if (!$this->verifyRecaptcha($recaptchaResponse)) {
                $form->addError('Ověření reCAPTCHA selhalo. Zkuste to prosím znovu.');
                return;
            }

            try {
                $this->sendMail(
                    (array) $data,
                    'Nová zpráva z kontaktního formuláře',
                    'contact'
                );
            } catch (\Throwable $e) {
//                bdump($e->getMessage(), 'Mail error');
//                bdump($e->getTrace(), 'Trace');
                $form->addError('Nepodařilo se odeslat zprávu. Zkuste to prosím později.');
                return;
            }

            $onSuccess();
        };

        return $form;
    }

    public function getSiteKey(): string
    {
        return $this->recaptchaSiteKey;
    }

    private function verifyRecaptcha(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'secret'   => $this->recaptchaSecretKey,
                    'response' => $token,
                    'remoteip' => $this->httpRequest->getRemoteAddress(),
                ]),
                'timeout' => 5,
            ],
        ]);

        $result = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);

        if ($result === false) {
            bdump('reCAPTCHA: Google unreachable');
            return false;
        }

        $json = json_decode($result, true);

        if (!isset($json['success']) || $json['success'] !== true) {
            return false;
        }

        if (!isset($json['score']) || $json['score'] < 0.5) {
            bdump($json['score'] ?? 'no score', 'reCAPTCHA score too low');
            return false;
        }

        return true;
    }
}
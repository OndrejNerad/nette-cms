<?php declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Form;
use Nette\Http\IRequest;
use Nette\Mail\Message;

abstract class BaseFormFactory
{
    public function __construct(
        protected readonly \Nette\Mail\Mailer $mailer,
        protected readonly string $adminEmail,
        protected readonly string $recaptchaSiteKey,
        protected readonly string $recaptchaSecretKey,
        protected readonly bool $recaptchaVerify,
        protected readonly IRequest $httpRequest,
    ) {}

    public function getSiteKey(): string
    {
        return $this->recaptchaSiteKey;
    }

    protected function addCommonFields(Form $form): void
    {
        $form->addText('address')
            ->setHtmlAttribute('style', 'display:none')
            ->setOmitted();
    }

    protected function verifyRecaptcha(?string $token): bool
    {
        if (!$this->recaptchaVerify) {
            return true;
        }

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
            return false;
        }

        $json = json_decode($result, true);

        return isset($json['success']) && $json['success'] === true;
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
        return '
        <h2>Nová zpráva z kontaktního formuláře</h2>
        <table>
            <tr><th>Jméno</th><td>' . htmlspecialchars($data['name']) . '</td></tr>
            <tr><th>E-mail</th><td>' . htmlspecialchars($data['email']) . '</td></tr>
            <tr><th>Telefon</th><td>' . htmlspecialchars($data['phone']) . '</td></tr>
            <tr><th>Zpráva</th><td>' . nl2br(htmlspecialchars($data['message'])) . '</td></tr>
        </table>
    ';
    }
}

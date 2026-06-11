<?php declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Form;
use Nette\Mail\Message;

final class CarInquiryFormFactory extends BaseFormFactory
{
    public function create(callable $onSuccess, string $action, string $carLabel, string $carId, string $lang = 'cs'): Form
    {
        $t = fn(string $key) => FormTranslations::get($lang, $key);

        $form = new Form;
        $form->setAction($action);

        $form->addHidden('carLabel')->setDefaultValue($carLabel);
        $form->addHidden('carId')->setDefaultValue($carId);

        $form->addText('name', $t('name_label'))->setRequired($t('name_required'));
        $form->addText('email', $t('email_label'))->setRequired($t('email_required'))->addRule(Form::EMAIL, $t('email_invalid'));
        $form->addText('phone', $t('phone_label'))->setRequired($t('phone_required'));
        $form->addTextArea('message', $t('message_label'))->setRequired($t('message_required'));

        $this->addCommonFields($form);

        $form->addSubmit('send', $t('submit_label'));

        $form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSuccess, $t): void {
            if (!empty($data->address)) {
                $onSuccess();
                return;
            }

            if (!$this->verifyRecaptcha($this->httpRequest->getPost('recaptcha_token'))) {
                $form->addError($t('recaptcha_error'));
                return;
            }

            try {
                $this->sendCarInquiryMail((array) $data);
            } catch (\Throwable) {
                $form->addError($t('send_error'));
                return;
            }

            $onSuccess();
        };

        return $form;
    }

    private function sendCarInquiryMail(array $data): void
    {
        $mail = new Message;
        $mail->setFrom($data['email'], $data['name'])
            ->addTo($this->adminEmail)
            ->setSubject('Poptávka vozidla: ' . $data['carLabel'])
            ->setHtmlBody('
                <h2>Poptávka vozidla</h2>
                <table>
                    <tr><th>Vozidlo</th><td>' . htmlspecialchars($data['carLabel']) . '</td></tr>
                    <tr><th>ID vozidla</th><td>' . htmlspecialchars($data['carId']) . '</td></tr>
                </table>
                <h3>Kontakt</h3>
                <table>
                    <tr><th>Jméno</th><td>' . htmlspecialchars($data['name']) . '</td></tr>
                    <tr><th>E-mail</th><td>' . htmlspecialchars($data['email']) . '</td></tr>
                    <tr><th>Telefon</th><td>' . htmlspecialchars($data['phone']) . '</td></tr>
                    <tr><th>Zpráva</th><td>' . nl2br(htmlspecialchars($data['message'])) . '</td></tr>
                </table>
            ');

        $this->mailer->send($mail);
    }
}

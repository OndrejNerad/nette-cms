<?php declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Form;
use Nette\Mail\Message;

final class AutoInspectFormFactory extends BaseFormFactory
{
    public function create(callable $onSuccess, string $action, string $lang = 'cs'): Form
    {
        $t = fn(string $key) => FormTranslations::get($lang, $key);

        $form = new Form;
        $form->setAction($action);

        $form->addText('name', $t('name_label'))->setRequired($t('name_required'));
        $form->addText('email', $t('email_label'))->setRequired($t('email_required'))->addRule(Form::EMAIL, $t('email_invalid'));
        $form->addText('phone', $t('phone_label'))->setRequired($t('phone_required'));
        $form->addSelect('package', $t('package_label'), [
            'package-1' => $t('package_1'),
            'package-2' => $t('package_2'),
            'package-3' => $t('package_3'),
        ])->setRequired(true);
        $form->addTextArea('note', $t('note_label'))->setRequired(false);

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
                $this->sendAutoInspectMail((array) $data);
            } catch (\Throwable) {
                $form->addError($t('send_error'));
                return;
            }

            $onSuccess();
        };

        return $form;
    }

    private function sendAutoInspectMail(array $data): void
    {
        $packageLabels = ['package-1' => 'Balíček 1', 'package-2' => 'Balíček 2', 'package-3' => 'Balíček 3'];
        $packageLabel = $packageLabels[$data['package']] ?? $data['package'];

        $mail = new Message;
        $mail->setFrom($data['email'], $data['name'])
            ->addTo($this->adminEmail)
            ->setSubject('Nová poptávka AutoInspect: ' . $packageLabel)
            ->setHtmlBody('
                <h2>Nová poptávka AutoInspect</h2>
                <table>
                    <tr><th>Jméno</th><td>' . htmlspecialchars($data['name']) . '</td></tr>
                    <tr><th>E-mail</th><td>' . htmlspecialchars($data['email']) . '</td></tr>
                    <tr><th>Telefon</th><td>' . htmlspecialchars($data['phone']) . '</td></tr>
                    <tr><th>Balíček</th><td>' . htmlspecialchars($packageLabel) . '</td></tr>
                    <tr><th>Poznámka</th><td>' . nl2br(htmlspecialchars($data['note'] ?? '')) . '</td></tr>
                </table>
            ');

        $this->mailer->send($mail);
    }
}

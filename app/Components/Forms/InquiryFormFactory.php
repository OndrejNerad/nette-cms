<?php declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Form;
use Nette\Mail\Message;

final class InquiryFormFactory extends BaseFormFactory
{
    private const PALIVO_LABELS = ['0' => 'Benzín', '1' => 'Diesel', '2' => 'CNG', '3' => 'LPG', '4' => 'Hybrid', '5' => 'Elektro'];
    private const DPH_LABELS    = ['0' => 'Ano', '1' => 'Ne'];
    private const VELIKOST_LABELS = ['0' => 'Velké', '1' => 'Malé'];

    public function create(callable $onSuccess, string $action, string $lang = 'cs'): Form
    {
        $t = fn(string $key) => FormTranslations::get($lang, $key);

        $form = new Form;
        $form->setAction($action);

        $form->addText('priceMin')->setRequired(false);
        $form->addText('priceMax')->setRequired(false);
        $form->addText('dph')->setRequired(false);
        $form->addText('velikost')->setRequired(false);
        $form->addText('pocetDveri')->setRequired(false);
        $form->addText('pocetMist')->setRequired(false);
        $form->addText('palivo')->setRequired(false);
        $form->addText('name', $t('name_label'))->setRequired($t('name_required'));
        $form->addText('email', $t('email_label'))->setRequired($t('email_required'))->addRule(Form::EMAIL, $t('email_invalid'));
        $form->addText('phone', $t('phone_label'))->setRequired($t('phone_required'));
        $form->addTextArea('spatnaZkusenost')->setRequired(false);
        $form->addTextArea('darek')->setRequired(false);
        $form->addTextArea('termin')->setRequired(false);

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

            // karoserie[] checkboxes are plain HTML (no Nette namespace) — read from raw POST
            $karoserie = (array) ($this->httpRequest->getPost('karoserie') ?? []);

            try {
                $this->sendInquiryMail((array) $data, $karoserie);
            } catch (\Throwable) {
                $form->addError($t('send_error'));
                return;
            }

            $onSuccess();
        };

        return $form;
    }

    private function sendInquiryMail(array $data, array $karoserie): void
    {
        $mail = new Message;
        $mail->setFrom($data['email'], $data['name'])
            ->addTo($this->adminEmail)
            ->setSubject('Nová poptávka vozidla')
            ->setHtmlBody('
                <h2>Nová poptávka vozidla</h2>
                <table>
                    <tr><th>Jméno</th><td>' . htmlspecialchars($data['name']) . '</td></tr>
                    <tr><th>E-mail</th><td>' . htmlspecialchars($data['email']) . '</td></tr>
                    <tr><th>Telefon</th><td>' . htmlspecialchars($data['phone']) . '</td></tr>
                </table>
                <h3>Parametry vozidla</h3>
                <table>
                    <tr><th>Cena od</th><td>' . htmlspecialchars($data['priceMin'] ?? '') . ' Kč</td></tr>
                    <tr><th>Cena do</th><td>' . htmlspecialchars($data['priceMax'] ?? '') . ' Kč</td></tr>
                    <tr><th>Odpočet DPH</th><td>' . htmlspecialchars(self::DPH_LABELS[$data['dph'] ?? ''] ?? '') . '</td></tr>
                    <tr><th>Karoserie</th><td>' . htmlspecialchars(implode(', ', $karoserie)) . '</td></tr>
                    <tr><th>Velikost</th><td>' . htmlspecialchars(self::VELIKOST_LABELS[$data['velikost'] ?? ''] ?? '') . '</td></tr>
                    <tr><th>Počet dveří</th><td>' . htmlspecialchars($data['pocetDveri'] ?? '') . '</td></tr>
                    <tr><th>Počet míst</th><td>' . htmlspecialchars($data['pocetMist'] ?? '') . '</td></tr>
                    <tr><th>Palivo</th><td>' . htmlspecialchars(self::PALIVO_LABELS[$data['palivo'] ?? ''] ?? '') . '</td></tr>
                </table>
                <h3>Doplňující informace</h3>
                <table>
                    <tr><th>Špatná zkušenost</th><td>' . nl2br(htmlspecialchars($data['spatnaZkusenost'] ?? '')) . '</td></tr>
                    <tr><th>Dárek</th><td>' . nl2br(htmlspecialchars($data['darek'] ?? '')) . '</td></tr>
                    <tr><th>Termín</th><td>' . nl2br(htmlspecialchars($data['termin'] ?? '')) . '</td></tr>
                </table>
            ');

        $this->mailer->send($mail);
    }
}

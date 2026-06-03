<?php declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Form;

final class PricingFormFactory extends BaseFormFactory
{
    public function create(callable $onSuccess, string $action, string $lang = 'cs'): Form
    {
        $t = fn(string $key) => FormTranslations::get($lang, $key);

        $form = new Form;
        $form->setAction($action);

        // Step 1 — vehicle info
        $form->addText('znackaModel')->setRequired(false);
        $form->addText('rokVyroby')->setRequired(false);
        $form->addText('tachometr')->setRequired(false);
        $form->addText('motorizace')->setRequired(false);
        $form->addText('vin')->setRequired(false);
        $form->addText('cena')->setRequired(false);

        // Step 2 — additional info (selects already have names in HTML — read via getHtmlName)
        $form->addText('dph')->setRequired(false);
        $form->addText('history')->setRequired(false);
        $form->addText('service')->setRequired(false);
        $form->addText('interested')->setRequired(false);
        $form->addText('damaged')->setRequired(false);

        // Step 3 — contact
        $form->addText('name', $t('name_label'))->setRequired($t('name_required'));
        $form->addText('email', $t('email_label'))->setRequired($t('email_required'))->addRule(Form::EMAIL, $t('email_invalid'));
        $form->addText('phone', $t('phone_label'))->setRequired($t('phone_required'));
        $form->addUpload('tp')->setRequired(false);
        $form->addUpload('photos')->setRequired(false);

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
                $this->sendPricingMail((array) $data);
            } catch (\Throwable) {
                $form->addError($t('send_error'));
                return;
            }

            $onSuccess();
        };

        return $form;
    }

    private function sendPricingMail(array $data): void
    {
        $yesNo = ['1' => 'Ano', '0' => 'Ne'];

        $mail = new Message;
        $mail->setFrom($data['email'], $data['name'])
            ->addTo($this->adminEmail)
            ->setSubject('Nová poptávka ocenění vozidla')
            ->setHtmlBody('
                <h2>Nová poptávka ocenění vozidla</h2>
                <h3>Kontakt</h3>
                <table>
                    <tr><th>Jméno</th><td>' . htmlspecialchars($data['name']) . '</td></tr>
                    <tr><th>E-mail</th><td>' . htmlspecialchars($data['email']) . '</td></tr>
                    <tr><th>Telefon</th><td>' . htmlspecialchars($data['phone']) . '</td></tr>
                </table>
                <h3>Info o vozidle</h3>
                <table>
                    <tr><th>Značka a model</th><td>' . htmlspecialchars($data['znackaModel'] ?? '') . '</td></tr>
                    <tr><th>Uvedení do provozu</th><td>' . htmlspecialchars($data['rokVyroby'] ?? '') . '</td></tr>
                    <tr><th>Stav tachometru</th><td>' . htmlspecialchars($data['tachometr'] ?? '') . '</td></tr>
                    <tr><th>Motorizace</th><td>' . htmlspecialchars($data['motorizace'] ?? '') . '</td></tr>
                    <tr><th>VIN</th><td>' . htmlspecialchars($data['vin'] ?? '') . '</td></tr>
                    <tr><th>Představa o ceně</th><td>' . htmlspecialchars($data['cena'] ?? '') . '</td></tr>
                </table>
                <h3>Doplňující informace</h3>
                <table>
                    <tr><th>Odpočet DPH</th><td>' . htmlspecialchars($yesNo[$data['dph'] ?? ''] ?? '') . '</td></tr>
                    <tr><th>Vedená historie</th><td>' . htmlspecialchars($yesNo[$data['history'] ?? ''] ?? '') . '</td></tr>
                    <tr><th>Poslední servis</th><td>' . htmlspecialchars($yesNo[$data['service'] ?? ''] ?? '') . '</td></tr>
                    <tr><th>Mám zájem o</th><td>' . htmlspecialchars($yesNo[$data['interested'] ?? ''] ?? '') . '</td></tr>
                    <tr><th>Poškozeno</th><td>' . htmlspecialchars($yesNo[$data['damaged'] ?? ''] ?? '') . '</td></tr>
                </table>
            ');

        /** @var \Nette\Http\FileUpload $tp */
        $tp = $data['tp'] ?? null;
        if ($tp instanceof \Nette\Http\FileUpload && $tp->isOk()) {
            $mail->addAttachment($tp->getUntrustedName(), $tp->getContents());
        }

        /** @var \Nette\Http\FileUpload $photos */
        $photos = $data['photos'] ?? null;
        if ($photos instanceof \Nette\Http\FileUpload && $photos->isOk()) {
            $mail->addAttachment($photos->getUntrustedName(), $photos->getContents());
        }

        $this->mailer->send($mail);
    }
}

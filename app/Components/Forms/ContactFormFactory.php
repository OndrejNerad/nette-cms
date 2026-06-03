<?php declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Form;

final class ContactFormFactory extends BaseFormFactory
{
    public function create(callable $onSuccess, string $action, string $lang = 'cs'): Form
    {
        $t = fn(string $key) => FormTranslations::get($lang, $key);

        $form = new Form;
        $form->setAction($action);

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

            $recaptchaResponse = $this->httpRequest->getPost('recaptcha_token');
            if (!$this->verifyRecaptcha($recaptchaResponse)) {
                $form->addError($t('recaptcha_error'));
                return;
            }

            try {
                $this->sendMail((array) $data, 'Nová zpráva z kontaktního formuláře', 'contact');
            } catch (\Throwable) {
                $form->addError($t('send_error'));
                return;
            }

            $onSuccess();
        };

        return $form;
    }
}

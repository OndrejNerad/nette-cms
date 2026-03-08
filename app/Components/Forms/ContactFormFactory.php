<?php

declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Form;

final class ContactFormFactory extends BaseFormFactory
{
    public function create(callable $onSuccess, string $action): Form
    {
        $form = new Form;
        $form->setAction($action);

        $form->addText('name', 'Jméno')->setRequired();
        $form->addText('email', 'E-mail')->setRequired()->addRule(Form::EMAIL);
        $form->addText('phone', 'Telefon')->setRequired();
        $form->addTextArea('message', 'Poznámka')->setRequired();

        $this->addCommonFields($form);

        $form->addSubmit('send', 'Odeslat');

        $form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSuccess) {

            try {
                // TODO: fix for prod
                $this->sendMail(
                    (array) $data,
                    'Nová zpráva z kontaktního formuláře'
                );
            } catch (\Throwable $e) {
                // TODO: rework for prod
                bdump($e->getMessage(), 'Mail error');
                bdump($e->getTrace(), 'Trace');

                return; // stop here so you see the bdump - debug info
            }

            $onSuccess();
        };

        $form->onError[] = function (Form $form) {
            $form->addError('Prosím opravte chyby ve formuláři.');
        };

        return $form;
    }
}
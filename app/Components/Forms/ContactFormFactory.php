<?php

namespace App\Components\Forms;

use Nette\Application\UI\Form;

final class ContactFormFactory extends BaseFormFactory
{
    public function create(callable $onSuccess): Form
    {
        $form = new Form;

        $form->addText('name', 'Jméno:')->setRequired();
        $form->addText('email', 'E-mail:')->setRequired()->addRule(Form::EMAIL);
        $form->addText('phone', 'Telefon:')->setRequired();
        $form->addTextArea('message', 'Zpráva:')->setRequired();

        $this->addCommonFields($form);

        $form->addSubmit('send', 'Odeslat');

        $form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSuccess) {
            if ($data->address !== '') return; // bot

            $this->sendMail(
                (array)$data,
                'Nová zpráva z kontaktního formuláře'
            );

            $onSuccess();
        };

        return $form;
    }
}
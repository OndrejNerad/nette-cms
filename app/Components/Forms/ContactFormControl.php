<?php

declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;

final class ContactFormControl extends Control
{
    public function __construct(
        private readonly ContactFormFactory $factory
    ) {}

    public function render(): void
    {
        $this->template->render(__DIR__ . '/templates/contactForm.latte');
    }

    protected function createComponentContactForm(): Form
    {
        return $this->factory->create(
            function () {
                $this->presenter->flashMessage('Děkujeme! Zpráva byla odeslána.', 'success');
                $this->presenter->redirect('this');
            },
            $this->presenter->getHttpRequest()->getUrl()->getPath()
        );
    }
}
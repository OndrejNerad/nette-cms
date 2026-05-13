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
        $this->template->recaptchaSiteKey = $this->factory->getSiteKey();
        $this->template->render(__DIR__ . '/templates/contactForm.latte');
    }

    protected function createComponentContactForm(): Form
    {
        return $this->factory->create(
            function (): void {
                $this->presenter->flashMessage('Děkujeme! Zpráva byla odeslána.', 'success');

                if ($this->presenter->isAjax()) {
                    $this->redrawControl('contactFormSnippet');
                } else {
                    $this->presenter->redirectUrl(
                        $this->presenter->getHttpRequest()->getUrl()->getAbsoluteUrl()
                    );
                }
            },
            $this->presenter->getHttpRequest()->getUrl()->getPath()
        );
    }
}
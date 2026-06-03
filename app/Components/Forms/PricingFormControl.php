<?php declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;

final class PricingFormControl extends Control
{
    public function __construct(
        private readonly PricingFormFactory $factory,
        private readonly string $lang,
    ) {}

    public function render(): void
    {
        $this->template->recaptchaSiteKey = $this->factory->getSiteKey();
        $this->template->lang = $this->lang;
        $this->template->render(__DIR__ . '/templates/pricingForm.latte');
    }

    protected function createComponentPricingForm(): Form
    {
        $form = $this->factory->create(
            function (): void {
                $this->presenter->flashMessage(FormTranslations::get($this->lang, 'pricing_success'), 'success');

                if ($this->presenter->isAjax()) {
                    $this->redrawControl('pricingFormSnippet');
                } else {
                    $this->presenter->redirectUrl(
                        $this->presenter->getHttpRequest()->getUrl()->getAbsoluteUrl()
                    );
                }
            },
            $this->presenter->getHttpRequest()->getUrl()->getPath(),
            $this->lang,
        );

        $form->onSubmit[] = function () {
            if ($this->presenter->isAjax()) {
                $this->redrawControl('pricingFormSnippet');
            }
        };

        return $form;
    }
}

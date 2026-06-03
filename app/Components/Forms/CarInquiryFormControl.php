<?php declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;

final class CarInquiryFormControl extends Control
{
    private string $carLabel = '';
    private string $carId = '';

    public function __construct(
        private readonly CarInquiryFormFactory $factory,
        private readonly string $lang,
    ) {}

    public function setCarInfo(string $carLabel, string $carId): void
    {
        $this->carLabel = $carLabel;
        $this->carId = $carId;
    }

    public function render(): void
    {
        $this->template->carLabel = $this->carLabel;
        $this->template->recaptchaSiteKey = $this->factory->getSiteKey();
        $this->template->lang = $this->lang;
        $this->template->render(__DIR__ . '/templates/carInquiryForm.latte');
    }

    protected function createComponentCarInquiryForm(): Form
    {
        $form = $this->factory->create(
            function (): void {
                $this->presenter->flashMessage(FormTranslations::get($this->lang, 'car_inquiry_success'), 'success');

                if ($this->presenter->isAjax()) {
                    $this->redrawControl('carInquiryFormSnippet');
                } else {
                    $this->presenter->redirectUrl(
                        $this->presenter->getHttpRequest()->getUrl()->getAbsoluteUrl() . '#car-inquiry-form'
                    );
                }
            },
            $this->presenter->getHttpRequest()->getUrl()->getPath(),
            $this->carLabel,
            $this->carId,
            $this->lang,
        );

        $form->onSubmit[] = function () {
            if ($this->presenter->isAjax()) {
                $this->redrawControl('carInquiryFormSnippet');
            }
        };

        return $form;
    }
}

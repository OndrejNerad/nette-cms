<?php declare(strict_types=1);

namespace App\Components\Forms;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;

final class AutoInspectFormControl extends Control
{
    public function __construct(
        private readonly AutoInspectFormFactory $factory,
        private readonly string $lang,
    ) {}

    public function render(): void
    {
        $this->template->lang = $this->lang;
        $this->template->recaptchaSiteKey = $this->factory->getSiteKey();
        $this->template->render(__DIR__ . '/templates/autoInspectForm.latte');
    }

    protected function createComponentAutoInspectForm(): Form
    {
        $form = $this->factory->create(
            function (): void {
                $this->presenter->flashMessage(FormTranslations::get($this->lang, 'autoinspect_success'), 'success');

                if ($this->presenter->isAjax()) {
                    $this->redrawControl('autoInspectFormSnippet');
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
                $this->redrawControl('autoInspectFormSnippet');
            }
        };

        return $form;
    }
}

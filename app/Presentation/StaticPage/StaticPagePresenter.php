<?php

declare(strict_types=1);

namespace App\Presentation\StaticPage;

use App\Model\Orm;
use Nette;
use Nette\Application\UI\Presenter;
use App\Components\Forms\ContactFormControl;
use App\Components\Forms\ContactFormControlFactory;
use App\Components\Forms\InquiryFormControl;
use App\Components\Forms\InquiryFormControlFactory;
use App\Components\Forms\PricingFormControl;
use App\Components\Forms\PricingFormControlFactory;
use App\Components\Forms\AutoInspectFormControl;
use App\Components\Forms\AutoInspectFormControlFactory;

final class StaticPagePresenter extends Presenter
{
    public function __construct(
        private readonly ContactFormControlFactory $contactFormControlFactory,
        private readonly InquiryFormControlFactory $inquiryFormControlFactory,
        private readonly PricingFormControlFactory $pricingFormControlFactory,
        private readonly AutoInspectFormControlFactory $autoInspectFormControlFactory,
        private readonly Orm $orm,
    ) {
        parent::__construct();
    }

    protected function createTemplate(?string $class = null): Nette\Application\UI\Template
    {
        $template = parent::createTemplate($class);
        $this->setLayout(__DIR__ . '/../@layout.latte');
        return $template;
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $url = $this->getParameter('url') ?? 'default';
        $lightNav = ['default', 'o-nas', 'about-us'];

        $this->template->lightNav = in_array($url, $lightNav, true);

        $this->template->homepage = false;
        if ($url === 'default') {
            $this->template->homepage = true;
        }

        $this->template->cars = $this->orm->cars->findRandom(4);
        $this->template->gReviews = $this->orm->googleReviews->findAll()->limitBy(8);
    }

    public function actionDefault(?string $url = null, string $lang = 'cs'): void
    {
        $this->template->lang = $lang;
        $this->template->url  = $url ?? 'default';
    }

    public function formatTemplateFiles(): array
    {
        $lang = $this->getParameter('lang') ?? 'cs';
        $url  = $this->getParameter('url') ?? 'default';

        return [
            __DIR__ . "/templates/$lang/$url.latte"
        ];
    }

    protected function createComponentContactForm(): ContactFormControl
    {
        return $this->contactFormControlFactory->create($this->getParameter('lang') ?? 'cs');
    }

    protected function createComponentInquiryForm(): InquiryFormControl
    {
        return $this->inquiryFormControlFactory->create($this->getParameter('lang') ?? 'cs');
    }

    protected function createComponentPricingForm(): PricingFormControl
    {
        return $this->pricingFormControlFactory->create($this->getParameter('lang') ?? 'cs');
    }

    protected function createComponentAutoInspectForm(): AutoInspectFormControl
    {
        return $this->autoInspectFormControlFactory->create($this->getParameter('lang') ?? 'cs');
    }
}
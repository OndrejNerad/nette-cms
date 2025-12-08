<?php

declare(strict_types=1);

namespace App\Presentation\StaticPage;

use Nette;
use Nette\Application\UI\Presenter;

final class StaticPagePresenter extends Presenter
{
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
    }

    public function actionDefault(?string $url = null, string $lang = 'cs'): void
    {
        $this->template->lang = $lang;
        $this->template->url  = $url ?? 'default';
    }

    public function formatTemplateFiles(): array
    {
        $lang = $this->getParameter('lang');
        $url  = $this->getParameter('url') ?? 'default';

        return [
            __DIR__ . "/templates/$lang/$url.latte"
        ];
    }
}
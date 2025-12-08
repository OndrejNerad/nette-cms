<?php

declare(strict_types=1);

namespace App\Presentation\Error\Error4xx;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Presenter;
use Nette\Application\UI\Template;

final class Error4xxPresenter extends Presenter
{
    public function renderDefault(BadRequestException $exception): void
    {
        $path = $this->getHttpRequest()->getUrl()->getPath();
        $lang = (strpos($path, '/en') === 0 || strpos($path, '/en/') === 0) ? 'en' : 'cs';
        $this->template->setFile(__DIR__ . "/templates/$lang/404.latte");

        $this->template->lang = $lang;
        $code = $exception->getCode() ?: 404;
        $this->getTemplate()->httpCode = $code;
        $this->getHttpResponse()->setCode($code);
    }

    protected function createTemplate(?string $class = null): Template
    {
        $template = parent::createTemplate($class);
        $this->setLayout(__DIR__ . '/../../@layout.latte');
        $template->lightNav = false;
        return $template;
    }
}
<?php declare(strict_types=1);

namespace App\Components\Forms;

interface AutoInspectFormControlFactory
{
    public function create(string $lang): AutoInspectFormControl;
}

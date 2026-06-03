<?php declare(strict_types=1);

namespace App\Components\Forms;

interface InquiryFormControlFactory
{
    public function create(string $lang): InquiryFormControl;
}

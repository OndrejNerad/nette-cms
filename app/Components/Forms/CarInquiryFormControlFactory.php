<?php declare(strict_types=1);

namespace App\Components\Forms;

interface CarInquiryFormControlFactory
{
    public function create(string $lang): CarInquiryFormControl;
}

<?php declare(strict_types=1);

namespace App\Components\Forms;

final class FormTranslations
{
    private const STRINGS = [
        'cs' => [
            'name_label'        => 'Jméno',
            'email_label'       => 'E-mail',
            'phone_label'       => 'Telefon',
            'message_label'     => 'Zpráva',
            'submit_label'      => 'Odeslat',
            'name_required'     => 'Zadejte prosím jméno.',
            'email_required'    => 'Zadejte prosím e-mail.',
            'email_invalid'     => 'E-mail není platný.',
            'phone_required'    => 'Zadejte prosím telefon.',
            'message_required'  => 'Napište prosím zprávu.',
            'recaptcha_error'   => 'Ověření reCAPTCHA selhalo. Zkuste to prosím znovu.',
            'send_error'        => 'Nepodařilo se odeslat zprávu. Zkuste to prosím později.',
            'contact_success'   => 'Děkujeme! Zpráva byla odeslána.',
            'inquiry_success'   => 'Děkujeme! Vaše poptávka byla odeslána.',
            'pricing_success'   => 'Děkujeme! Vaše poptávka byla odeslána.',
            'car_inquiry_success' => 'Děkujeme! Vaše poptávka byla odeslána.',
            'autoinspect_success' => 'Děkujeme! Zpráva byla odeslána.',
            'note_label'          => 'Poznámka',
            'package_label'       => 'Vybraný balíček',
            'package_1'           => 'Balíček 1',
            'package_2'           => 'Balíček 2',
            'package_3'           => 'Balíček 3',
        ],
        'en' => [
            'name_label'        => 'Name',
            'email_label'       => 'E-mail',
            'phone_label'       => 'Phone',
            'message_label'     => 'Message',
            'submit_label'      => 'Send',
            'name_required'     => 'Please enter your name.',
            'email_required'    => 'Please enter your e-mail.',
            'email_invalid'     => 'E-mail is not valid.',
            'phone_required'    => 'Please enter your phone number.',
            'message_required'  => 'Please write your message.',
            'recaptcha_error'   => 'reCAPTCHA verification failed. Please try again.',
            'send_error'        => 'Failed to send the message. Please try again later.',
            'contact_success'   => 'Thank you! Your message has been sent.',
            'inquiry_success'   => 'Thank you! Your inquiry has been sent.',
            'pricing_success'   => 'Thank you! Your inquiry has been sent.',
            'car_inquiry_success' => 'Thank you! Your inquiry has been sent.',
            'autoinspect_success' => 'Thank you! Your message has been sent.',
            'note_label'          => 'Note',
            'package_label'       => 'Selected package',
            'package_1'           => 'Package 1',
            'package_2'           => 'Package 2',
            'package_3'           => 'Package 3',
        ],
    ];

    public static function get(string $lang, string $key): string
    {
        return self::STRINGS[$lang][$key] ?? self::STRINGS['cs'][$key] ?? $key;
    }
}

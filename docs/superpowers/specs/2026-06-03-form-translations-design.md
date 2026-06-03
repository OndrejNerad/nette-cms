# Form Translations Design

**Date:** 2026-06-03

## Problem

The site serves both Czech (`/cs/...`) and English (`/en/...`) routes. Page templates are split into `templates/cs/` and `templates/en/`, and all UI text uses `{if $lang == 'en'}...{else}...{/if}`. However, the four form factories (`ContactFormFactory`, `InquiryFormFactory`, `CarInquiryFormFactory`, `PricingFormFactory`) hardcode Czech strings — field labels, validation error messages, reCAPTCHA errors, submit button labels, and flash messages — so EN pages still render Czech form text.

## Solution: Pass `$lang` through the chain

Extend the existing `$lang` pattern to the form layer. No new packages.

## Architecture

### 1. `FormTranslations` class

**Location:** `app/Components/Forms/FormTranslations.php`

A final class with a static 2D array of all translatable form strings, indexed by language then key. A single `get(string $lang, string $key): string` method returns the string, falling back to `'cs'` if the lang or key is missing.

**Keys:**

| Key | CS | EN |
|---|---|---|
| `name_label` | Jméno | Name |
| `email_label` | E-mail | E-mail |
| `phone_label` | Telefon | Phone |
| `message_label` | Zpráva | Message |
| `submit_label` | Odeslat | Send |
| `name_required` | Zadejte prosím jméno. | Please enter your name. |
| `email_required` | Zadejte prosím e-mail. | Please enter your e-mail. |
| `email_invalid` | E-mail není platný. | E-mail is not valid. |
| `phone_required` | Zadejte prosím telefon. | Please enter your phone number. |
| `message_required` | Napište prosím zprávu. | Please write your message. |
| `recaptcha_error` | Ověření reCAPTCHA selhalo. Zkuste to prosím znovu. | reCAPTCHA verification failed. Please try again. |
| `send_error` | Nepodařilo se odeslat zprávu. Zkuste to prosím později. | Failed to send the message. Please try again later. |
| `contact_success` | Děkujeme! Zpráva byla odeslána. | Thank you! Your message has been sent. |
| `inquiry_success` | Děkujeme! Vaše poptávka byla odeslána. | Thank you! Your inquiry has been sent. |
| `pricing_success` | Děkujeme! Vaše poptávka byla odeslána. | Thank you! Your inquiry has been sent. |
| `car_inquiry_success` | Děkujeme! Vaše poptávka byla odeslána. | Thank you! Your inquiry has been sent. |

### 2. ControlFactory interfaces — add `$lang` parameter

Each of the four ControlFactory interfaces gains `string $lang` on `create()`:

```php
interface ContactFormControlFactory {
    public function create(string $lang): ContactFormControl;
}
```

Nette DI auto-generates the implementation; adding the parameter to the interface is sufficient.

### 3. Controls — store and propagate `$lang`

Each Control receives `$lang` as a second constructor argument (Nette DI passes it via the factory's `create($lang)` call). Controls use it in two places:

- **Building the form:** passed as a final argument to `FormFactory::create(..., $lang)`
- **Flash message:** `FormTranslations::get($this->lang, 'contact_success')` in the `onSuccess` closure

```php
final class ContactFormControl extends Control
{
    public function __construct(
        private readonly ContactFormFactory $factory,
        private readonly string $lang,
    ) {}

    protected function createComponentContactForm(): Form
    {
        $form = $this->factory->create(
            function (): void {
                $this->presenter->flashMessage(
                    FormTranslations::get($this->lang, 'contact_success'),
                    'success'
                );
                // ...
            },
            $this->presenter->getHttpRequest()->getUrl()->getPath(),
            $this->lang,
        );
        // ...
    }
}
```

### 4. FormFactories — accept and use `$lang`

Each factory's `create()` gains a `string $lang = 'cs'` final parameter. A local helper keeps field definitions readable:

```php
public function create(callable $onSuccess, string $action, string $lang = 'cs'): Form
{
    $t = fn(string $key) => FormTranslations::get($lang, $key);

    $form->addText('name', $t('name_label'))->setRequired($t('name_required'));
    $form->addText('email', $t('email_label'))
        ->setRequired($t('email_required'))
        ->addRule(Form::EMAIL, $t('email_invalid'));
    // ...
    $form->addSubmit('send', $t('submit_label'));

    $form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSuccess, $t): void {
        // ...
        if (!$this->verifyRecaptcha(...)) {
            $form->addError($t('recaptcha_error'));
            return;
        }
        // ...
        $form->addError($t('send_error'));
    };
}
```

### 5. Presenters — read and pass `$lang`

Each presenter's `createComponentXxx()` reads the lang from the route parameter and passes it to the ControlFactory:

```php
protected function createComponentContactForm(): ContactFormControl
{
    $lang = $this->getParameter('lang') ?? 'cs';
    return $this->contactFormControlFactory->create($lang);
}
```

`getParameter()` is safe during component creation — Nette loads parameters before any component access.

## Scope

### In scope
- Field labels, validation errors, reCAPTCHA error, send error, flash messages, submit button label
- All four forms: Contact, Inquiry, CarInquiry, Pricing
- `StaticPagePresenter` and `CarPresenter`

### Out of scope
- **Email body content** (`sendMail`, `sendInquiryMail`, etc.) — sent to the Czech-speaking admin team, stays in Czech
- **`PALIVO_LABELS`, `DPH_LABELS`, `VELIKOST_LABELS`** in `InquiryFormFactory` — used only in the email body
- **Latte form templates** — Nette renders labels/errors from the form object; templates don't hardcode these strings

## Files changed

| File | Change |
|---|---|
| `app/Components/Forms/FormTranslations.php` | **New** — central translations class |
| `app/Components/Forms/ContactFormControlFactory.php` | Add `string $lang` to `create()` |
| `app/Components/Forms/InquiryFormControlFactory.php` | Add `string $lang` to `create()` |
| `app/Components/Forms/CarInquiryFormControlFactory.php` | Add `string $lang` to `create()` |
| `app/Components/Forms/PricingFormControlFactory.php` | Add `string $lang` to `create()` |
| `app/Components/Forms/ContactFormControl.php` | Store `$lang`, pass to factory, translate flash |
| `app/Components/Forms/InquiryFormControl.php` | Store `$lang`, pass to factory, translate flash |
| `app/Components/Forms/CarInquiryFormControl.php` | Store `$lang`, pass to factory, translate flash |
| `app/Components/Forms/PricingFormControl.php` | Store `$lang`, pass to factory, translate flash |
| `app/Components/Forms/ContactFormFactory.php` | Add `$lang` param, use `FormTranslations` |
| `app/Components/Forms/InquiryFormFactory.php` | Add `$lang` param, use `FormTranslations` |
| `app/Components/Forms/CarInquiryFormFactory.php` | Add `$lang` param, use `FormTranslations` |
| `app/Components/Forms/PricingFormFactory.php` | Add `$lang` param, use `FormTranslations` |
| `app/Presentation/StaticPage/StaticPagePresenter.php` | Pass `$lang` in all `createComponentXxx()` |
| `app/Presentation/Car/CarPresenter.php` | Pass `$lang` in `createComponentCarInquiryForm()` |

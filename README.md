# SilverStripe Custom Errors Module

## Overview

As an alternative to the Error Page module, this module provides themed error responses but makes developers responsible for the content of error messages instead of CMS users.

## Installation

```
$ composer require jonom/silverstripe-custom-errors
```

## Configuration

You can define default response content for each response code through the yml config API. Response codes need to be prefixed with the letter 'e' because numbers alone aren't valid SS config keys. Fields you specify will be passed through to a page template for rendering. You can specify a value only, or cast a value by specifying details in an arrray. Example:

```yaml
---
Name: my-custom-errors
After:
    - '#custom-errors'
---
JonoM\CustomErrors\CustomErrorControllerExtension:
  custom_fields:
    default:
      Content:
        Type: 'SilverStripe\ORM\FieldType\DBHTMLText'
        Value: '<p>Sorry, there was a problem with handling your request.</p>'
    e404:
      Title: 'Not Found'
      Content:
        Type: 'SilverStripe\ORM\FieldType\DBHTMLText'
        Value: '<p>Sorry, it seems you were trying to access a page that doesnʼt exist.</p><p>Please check the spelling of the URL you were trying to access and try again.</p>'
```

You can also specify a default controller and template for error responses.

```yaml
JonoM\CustomErrors\CustomErrorControllerExtension:
  default_controller: 'PageController'
  default_template: 'Page' # exclude .ss extension
```

## Custom error responses

You can call `$this->httpError($statusCode, $errorMessage)` from your controller and get a themed response, but if default content has been provided for the given status code, your `$errorMessage` won't be displayed. This is to ensure that you have some control over all of the error messages that a user may see, not just the ones that are triggered in your own code.

To return a custom error response from a controller, instead of calling `$this->httpError()` you can use `$this->customError()` and pass through custom fields the same way you would if using `renderWith()`. Example:

```php
if ($somethingWentWrong) {
    $this->customError(404, [
        'Title' => 'We couldn\'t find the droids you are looking for',
        'Content' => DBField::create_field('HTMLText', '<p>Please try another Cantina</p>')
    ]);
}
```

Any fields you specify are merged with the defaults, so you only need to specify fields that you want to override.

```php
$this->customError(404, [
    'Content' => DBField::create_field('HTMLText', '<p>Maybe try the Google</p>')
]);
```

You can also specify a controller and template to be used.

```php
$this->customError(
    404,
    [
        'Content' => DBField::create_field('HTMLText', '<p>Here are some other droids you may be interested in:</p>'),
        'Droids' => Droid::get()->filterAny(['Language' => 'Beeps', 'Color' => 'Gold'])->limit(5)
    ],
    'DroidHolder',
    'DroidHolderController'
);
```

## Maintainer contact

[Jono Menz](https://jonomenz.com)

## Sponsorship

If you want to boost morale of the maintainer you're welcome to make a small monthly donation through [**GitHub**](https://github.com/sponsors/jonom), or a one time donation through [**PayPal**](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Z5HEZREZSKA6A). ❤️ Thank you!

Please also feel free to [get in touch](https://jonomenz.com) if you want to hire the maintainer to develop a new feature, or discuss another opportunity.
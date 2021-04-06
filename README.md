<p align="center">
  <img src="https://airbrake-github-assets.s3.amazonaws.com/brand/airbrake-full-logo.png" width="200">
</p>

# PHPBrake

[![Build Status](https://travis-ci.org/airbrake/phpbrake.svg?branch=master)](https://travis-ci.org/airbrake/phpbrake)

## Features
PHPBrake is the official [Airbrake](https://airbrake.io) PHP error notifier.
PHPBrake supports PHP 5.4 and higher. PHPBrake includes many useful features
that give you control over when and what you send to Airbrake, you can:

- [Send notices from try-catch blocks in your code](https://github.com/airbrake/phpbrake#quickstart)
- [Add custom data to a notice](https://github.com/airbrake/phpbrake#adding-custom-data-to-the-notice)
- [Filter sensitive data from the notice](https://github.com/airbrake/phpbrake#filtering-sensitive-data-from-the-notice)
- [Ignore specific exceptions](https://github.com/airbrake/phpbrake#ignoring-specific-exceptions)
- [Configure an error handler to capture uncaught exceptions](https://github.com/airbrake/phpbrake#error-handler)
- [Integrate with monolog](https://github.com/airbrake/phpbrake#monolog-integration)
- [Integrate with Laravel](https://github.com/TheoKouzelis/laravel-airbrake)
- [Integrate with CakePHP 3.x](https://gist.github.com/mauriciovillalobos/01a97f9ee6179ad70b17d54f37cc5010)
- [Integrate with Symfony](https://github.com/aminin/airbrake-bundle)
- [Integrate with Zend](https://github.com/FrankHouweling/zend-airbrake)
- and more

## Installation

```bash
composer require airbrake/phpbrake
```

## Quickstart

```php
// Create new Notifier instance.
$notifier = new Airbrake\Notifier([
    'projectId' => 12345, // FIX ME
    'projectKey' => 'abcdefg' // FIX ME
]);

// Set global notifier instance.
Airbrake\Instance::set($notifier);

// Register error and exception handlers.
$handler = new Airbrake\ErrorHandler($notifier);
$handler->register();

// Somewhere in the app...
try {
    throw new Exception('hello from phpbrake');
} catch(Exception $e) {
    Airbrake\Instance::notify($e);
}
```

## API

Notifier API consists of 4 methods:
- `buildNotice` - builds [Airbrake notice](https://airbrake.io/docs/#create-notice-v3).
- `sendNotice` - sends notice to Airbrake.
- `notify` - shortcut for `buildNotice` and `sendNotice`.
- `addFilter` - adds filter that can modify and/or filter notices.

## Adding custom data to the notice

```php
$notifier->addFilter(function ($notice) {
    $notice['context']['environment'] = 'production';
    return $notice;
});
```

## Filtering sensitive data from the notice

```php
$notifier->addFilter(function ($notice) {
    if (isset($notice['params']['password'])) {
        $notice['params']['password'] = 'FILTERED';
    }
    return $notice;
});
```

## Ignoring specific exceptions

```php
$notifier->addFilter(function ($notice) {
    if ($notice['errors'][0]['type'] == 'MyExceptionClass') {
        // Ignore this exception.
        return null;
    }
    return $notice;
});
```

## Add user data to the notice

```php
$notifier->addFilter(function ($notice) {
    $notice['context']['user']['name'] = 'Avocado Jones';
    $notice['context']['user']['email'] = 'AJones@guacamole.com';
    $notice['context']['user']['id'] = 12345;
    return $notice;
});
```

## Setting severity

[Severity](https://airbrake.io/docs/airbrake-faq/what-is-severity/) allows
categorizing how severe an error is. By default, it's set to `error`. To
redefine severity, simply overwrite `context/severity` of a notice object. For
example:

```php
$notice = $notifier->buildNotice($e);
$notice['context']['severity'] = 'critical';
$notifier->sendNotice($notice);
```

## Error handler

Notifier can handle PHP errors, uncaught exceptions and shutdown. You can register appropriate handlers using following code:

```php
$handler = new Airbrake\ErrorHandler($notifier);
$handler->register();
```

Under the hood `$handler->register` does following:

```php
set_error_handler([$this, 'onError'], error_reporting());
set_exception_handler([$this, 'onException']);
register_shutdown_function([$this, 'onShutdown']);
```

## Laravel integration

See https://github.com/TheoKouzelis/laravel-airbrake

## Symfony integration

See https://github.com/aminin/airbrake-bundle

## CakePHP 3.x integration

See https://gist.github.com/mauriciovillalobos/01a97f9ee6179ad70b17d54f37cc5010

## Zend Framework integration

See https://github.com/FrankHouweling/zend-airbrake

## Monolog integration

```php
$log = new Monolog\Logger('billing');
$log->pushHandler(new Airbrake\MonologHandler($notifier));

$log->addError('charge failed', ['client_id' => 123]);
```

## Extra configuration options

### appVersion

The version of your application that you can pass to differentiate exceptions
between multiple versions. It's not set by default.

```php
$notifier = new Airbrake\Notifier([
    // ...
    'appVersion' => '1.2.3',
    // ...
]);
```

### host

By default, it is set to `api.airbrake.io`. A `host` is a web address containing a
scheme ("http" or "https"), a host and a port. You can omit the port (80 will be
assumed) and the scheme ("https" will be assumed).

```php
$notifier = new Airbrake\Notifier([
    // ...
    'host' => 'errbit.example.com', // put your errbit host here
    // ...
]);
```

### remoteConfig

Configures the remote configuration feature. Every 10 minutes the notifier
will make a GET request to Airbrake servers to fetching a JSON document
containing configuration settings for your project. The notifier will apply
these new settings at runtime. By default, it is enabled.

To disable this feature, configure your notifier with:

```php
$notifier = new Airbrake\Notifier([
    // ...
    'remoteConfig' => false,
    // ...
]);
```

Note: it is not recommended to disable this feature. It might negatively impact
how your notifier works. Please use this option with caution.

### rootDirectory

Configures the root directory of your project. Expects a String or a Pathname,
which represents the path to your project. Providing this option helps us to
filter out repetitive data from backtrace frames and link to GitHub files
from our dashboard.

```php
$notifier = new Airbrake\Notifier([
    // ...
    'rootDirectory' => '/var/www/project',
    // ...
]);
```

### environment

Configures the environment the application is running in. Helps the Airbrake
dashboard to distinguish between exceptions occurring in different
environments. By default, it's not set.

```php
$notifier = new Airbrake\Notifier([
    // ...
    'environment' => 'staging',
    // ...
]);
```

### httpClient

Configures the underlying http client that must implement `GuzzleHttp\ClientInterface`.

```php
// Supply your own client.
$client = new Airbrake\Http\GuzzleClient(
    new GuzzleHttp\Client(['timeout' => 3])
);

$notifier = new Airbrake\Notifier([
    // ...
    'httpClient' => $client,
    // ...
]);
```

### Filtering keys

With `keysBlocklist` option you can specify list of keys containing sensitive information that must be filtered out, e.g.:

```php
$notifier = new Airbrake\Notifier([
    // ...
    'keysBlocklist' => ['/secret/i', '/password/i'],
    // ...
]);
```

## Running tests

```bash
composer install
vendor/bin/phpunit
```

## PHPDoc
```bash
composer require phpdocumentor/phpdocumentor
vendor/bin/phpdoc -d src
firefox output/index.html
```

Contact
-------

In case you have a problem, question or a bug report, feel free to:

* [file an issue](https://github.com/airbrake/phpbrake/issues)
* [send us an email](mailto:support@airbrake.io)

## License

PHPBrake is licensed under [The MIT License (MIT)](LICENSE).

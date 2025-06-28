[![Latest Version](https://img.shields.io/packagist/v/lento/lentoapi.svg)](https://packagist.org/packages/lento/lentoapi)
![Build](https://github.com/LentoLeisenfeld/LentoApi/actions/workflows/build.yaml/badge.svg)
![Tests](https://github.com/LentoLeisenfeld/LentoApi/actions/workflows/tests.yaml/badge.svg)
[![PSR-3 Compatible](https://img.shields.io/badge/PSR--3-compatible-brightgreen.svg)](https://www.php-fig.org/psr/psr-3/)
[![PSR-4 Compatible](https://github.com/LentoLeisenfeld/LentoApi/actions/workflows/psr-4.yaml/badge.svg)](https://www.php-fig.org/psr/psr-4/)
![PHP Version](https://img.shields.io/badge/PHP-8.4-blue)
![License](https://img.shields.io/github/license/LentoLeisenfeld/LentoApi)

# LentoApi

A lightweight, modular PHP API framework with built-in routing, **Illuminate Database (Eloquent ORM)** integration, logging, CORS, and middleware support.

---

## Features

- Attribute-based routing and controllers
- Bundled **Illuminate Database (Eloquent ORM)** for powerful database interactions
- PSR-3 compatible logging with flexible loggers (file, stdout)
- Built-in CORS support
- Middleware pipeline support
- Swagger/OpenAPI integration
- Simple dependency injection using `#[Service]` and `#[Inject]` attributes

---

## Installation

Add LentoApi to your project via Composer:

```bash
composer require lento/lentoapi
```

If you want to develop LentoApi locally or use a custom path repository, configure `composer.json` accordingly.

Make sure to install dependencies and autoload:

```bash
composer install
composer dump-autoload -o
```

---

## Basic Usage

Create an `index.php` or front controller to bootstrap your API.

Here is an example usage that demonstrates routing, Illuminate Database ORM configuration, logging, CORS, and middleware:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Lento\LentoApi;
use Lento\ORM;
use Lento\Logging\{FileLogger, StdoutLogger, LogLevel};

// Set timezone to ensure correct timestamps
date_default_timezone_set('Europe/Berlin');

// Configure Illuminate Database (Eloquent ORM)
ORM::configure('sqlite:./database.sqlite');

// Register controllers
$controllers = [
    Lento\Swagger\SwaggerController::class,
    App\Controllers\HelloController::class
];

// Register services (dependency injection)
$services = [
    App\Services\MessageService::class,
    App\Services\UserService::class
];

// Initialize API with controllers and services
$api = new LentoApi($controllers, $services);

// Enable logging with multiple loggers and custom log levels
$api->enableLogging([
    new FileLogger([
        'path' => "./lento.log",
        'levels' => [
            LogLevel::ERROR,
            LogLevel::CRITICAL,
            LogLevel::ALERT,
            LogLevel::EMERGENCY
        ]
    ]),
    new StdoutLogger([
        'levels' => [
            LogLevel::INFO,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::DEBUG,
        ]
    ])
]);

// Enable CORS with configuration
$api->useCors([
    'allowOrigin' => 'https://yourdomain.com',
    'allowMethods' => 'GET, POST, OPTIONS',
    'allowHeaders' => 'Content-Type, Authorization',
    'allowCredentials' => true,
]);

// Register middleware example
$api->use(function ($request, $response, $next) {
    // Middleware logic here
    return $next();
});

// Start the API (dispatches the request)
$api->start();
```

---

## Dependency Injection (DI) Services

LentoApi provides a simple, annotation-based dependency injection system.

Use `#[Service]` to mark your class as injectable, and `#[Inject]` to declare dependencies.

Here’s an example of a service class that retrieves user data from the database and logs activity:

```php
<?php

namespace App\Services;

use Lento\Attributes\{Service, Inject};
use Lento\Logging\Logger;
use App\DTO\UserDTO;
use App\Entities\User;

#[Service]
class UserService {

    #[Inject]
    private Logger $logger;

    public function getUser(string $name): ?UserDto {
        $user = User::where('name', $name)->first();
        if ($user) {
            return new UserDto(
                ...$user->toArray()
            );
        }

        $this->logger->error("user not found");
        return null;
    }
}
```

### Notes:

- Services are automatically instantiated and injected into controllers or other services.
- You can register all services via the `$services` array passed to `LentoApi`.
- Logging, configuration, or other core services provided by Lento can also be injected.

---

## ORM Integration

LentoApi bundles **Illuminate Database** (Laravel’s Eloquent ORM) for powerful and expressive database access.
You can then use Eloquent models and queries in your services and controllers.

---


## Requirements

- PHP 8.4+
- Composer
- SQLite, MySQL, or other PDO-supported databases for ORM

---

## Contributing

Contributions welcome! Please open issues or pull requests on GitHub.

---

## License

MIT License

---

## Contact

For questions or support, open an issue or contact the maintainer.

---

*Happy coding with LentoApi!*

[![Latest Version](https://img.shields.io/packagist/v/lento/api.svg)](https://packagist.org/packages/lento/api)
![Build](https://github.com/LentoLeisenfeld/LentoApi/actions/workflows/build.yaml/badge.svg)
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
- OpenAPI/OpenAPI integration
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
    Lento\OpenAPI\OpenAPIController::class,
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

## ORM: Database Configuration

LentoApi includes a simple `ORM` utility for easy [Eloquent ORM](https://laravel.com/docs/eloquent) integration using `illuminate/database`.
**You only need one line to connect to any supported database!**

### Quick Usage

```php
use Lento\ORM;

// SQLite
ORM::configure('sqlite:./database.sqlite');
ORM::configure('sqlite::memory:');

// PostgreSQL
ORM::configure('pgsql:host=localhost;port=5432;dbname=mydb;user=myuser;password=secret');

// MySQL/MariaDB
ORM::configure('mysql:host=localhost;port=3306;dbname=mydb;user=root;password=secret;charset=utf8mb4');

// Microsoft SQL Server
ORM::configure('sqlsrv:Server=localhost;Database=mydb;User=sa;Password=secret');
ORM::configure('mssql:host=localhost;port=1433;dbname=mydb;user=sa;password=secret');
```

After calling `ORM::configure(...)`, you can use [Eloquent models and queries](https://laravel.com/docs/eloquent) anywhere in your app.

---

### Supported DSN Formats

| Driver      | Example DSN                                                                            |
|-------------|----------------------------------------------------------------------------------------|
| SQLite      | `sqlite:./database.sqlite` <br> `sqlite::memory:`                                      |
| PostgreSQL  | `pgsql:host=localhost;port=5432;dbname=test;user=me;password=secret`                   |
| MySQL       | `mysql:host=localhost;port=3306;dbname=test;user=root;password=secret;charset=utf8mb4` |
| SQL Server  | `sqlsrv:Server=localhost;Database=test;User=sa;Password=secret` <br> `mssql:...`       |

---

### Optional Dependency

> **Note:**
> `illuminate/database` is only required if you use the ORM.
> If not installed, calling `ORM::configure()` will throw a clear error.
>
> Install with:
> ```bash
> composer require illuminate/database
> ```

---

### Custom Options

You can extend/modify the ORM class to support more options and DSN variations.
See the `ORM` source for driver-specific parsing and available connection parameters.

---

## ORM Cheatsheet: Models and Queries

After `ORM::configure(...)`, you can use [Eloquent models](https://laravel.com/docs/eloquent) as in Laravel.

### 1. Defining a Model

Create a model class extending `\Illuminate\Database\Eloquent\Model`:

```php
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users'; // optional if table name matches class name
    protected $fillable = ['name', 'email']; // allow mass assignment
    public $timestamps = false; // set true if you have created_at/updated_at columns
}
```

### 2. Basic Queries

```php
// Fetch all users
$users = User::all();

// Find by primary key
$user = User::find(1);

// Where clause
$user = User::where('email', 'test@example.com')->first();

// Insert
$newUser = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

// Update
$user->name = 'Bob';
$user->save();

// Delete
$user->delete();
```

### 3. Relationships

```php
class Post extends Model {
    public function user() {
        return $this->belongsTo(User::class);
    }
}

class User extends Model {
    public function posts() {
        return $this->hasMany(Post::class);
    }
}

// Usage:
$user = User::find(1);
$posts = $user->posts;

$post = Post::find(1);
$author = $post->user;
```

### 4. Query Builder

You can use Eloquent's query builder for complex queries:

```php
$results = User::where('created_at', '>', '2024-01-01')
    ->orderBy('name')
    ->limit(10)
    ->get();
```

### 5. Raw SQL

```php
use Illuminate\Support\Facades\DB;

$users = DB::select('select * from users where active = ?', [1]);
```

---

**For more, see the [Laravel Eloquent documentation](https://laravel.com/docs/eloquent).**

---

## Parameter Binding Attributes

LentoApi supports advanced, type-safe parameter injection for your controller methods.
You can use the following attributes to explicitly bind route, query, or body parameters to your method arguments:

### Supported Attributes

| Attribute                | Description                                    | Example Use                        |
|--------------------------|------------------------------------------------|-------------------------------------|
| `#[Param(source: "...")]`| Universal; supports `route`, `query`, `body`   | `#[Param(source: "body")]`          |
| `#[Route]`               | Shorthand for route/path parameters            | `#[Route] string $id`               |
| `#[Query]`               | Shorthand for query string (?foo=) parameters  | `#[Query] string $search`           |
| `#[Body]`                | Shorthand for body (JSON/form) parameters      | `#[Body] UserDTO $user`             |

---

### Usage Example

```php
use Lento\Attributes\{Route, Query, Body, Param};

class UserController
{
    public function getProfile(
        #[Route] string $userId,                   // from /users/{userId}
        #[Query] ?string $expand = null,           // from ?expand=...
        #[Body] UserProfileUpdateDTO $payload      // from POST/PUT body (JSON or form)
    ) {
        // ...
    }

    public function legacyExample(
        #[Param(source: "route")] string $userId,
        #[Param(source: "query")] ?string $token,
        #[Param(source: "body")] LoginInputDTO $data
    ) {
        // ...
    }
}
```

---

### How It Works

- `#[Route]`: Binds the parameter to a route placeholder (e.g., `/user/{id}` → `$id`).
- `#[Query]`: Binds the parameter to a query string value (e.g., `?token=...`).
- `#[Body]`: Binds the parameter to a property in the request body (POST/PUT JSON or form data).
  - If the parameter type is a class, it is auto-instantiated with the body data.
- `#[Param]`: A universal attribute where you can specify `source` (`route`, `query`, or `body`).
  Useful for compatibility or for dynamic scenarios.

#### Parameter name overrides

You can also specify a custom parameter name:

```php
public function getUser(
    #[Route("uuid")] string $userId  // binds route {uuid} to $userId
) { ... }
```
Or:
```php
public function find(
    #[Query("q")] string $queryTerm  // binds ?q=... to $queryTerm
) { ... }
```

---

### Best Practice

- Prefer `#[Route]`, `#[Query]`, and `#[Body]` for readability and clarity.
- Use `#[Param(source: "...")]` for maximum control or legacy compatibility.

---

**See the `/src/Lento/Attributes` directory for attribute definitions.**



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

<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Keep the test run out of the development database
|--------------------------------------------------------------------------
|
| Laravel's env() reads $_SERVER before anything else, and PHPUnit's <env>
| entries only reach putenv() and $_ENV. On a machine whose shell exports the
| project's .env — as this one does — $_SERVER still holds APP_ENV=local and
| DB_CONNECTION=mysql, so the suite would boot against the development
| database and RefreshDatabase would drop every table in it.
|
| Copying what PHPUnit set into $_SERVER closes that gap, so `php artisan test`
| is safe regardless of what the surrounding shell exports.
|
*/

foreach ($_ENV as $key => $value) {
    $_SERVER[$key] = $value;
}

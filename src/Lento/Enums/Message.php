<?php

namespace Lento\Enums;

// phpcs:disable Generic.Files.LineLength
enum Message: string
{
    #region Exceptions
    case Forbidden = "Forbidden";
    case NotFound = "Not Found";
    case Unauthorized = "Unauthorized";
    case ValidationFailed = "Validation failed";
    #endregion

    #region ORM
    case IlluminateNotInstalled = "illuminate/database is not installed. Please run 'composer require illuminate/database'.";
    #endregion
}
// phpcs:enable Generic.Files.LineLength
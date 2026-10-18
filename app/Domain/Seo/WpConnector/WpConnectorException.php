<?php

namespace App\Domain\Seo\WpConnector;

use Exception;

class WpConnectorException extends Exception
{
    public static function invalidCode(): self
    {
        return new self('کد اتصال نامعتبر است یا منقضی شده. در CRM یک کد تازه بسازید.');
    }
}

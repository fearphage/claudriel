<?php

declare(strict_types=1);

namespace Claudriel\Routing;

final class RequestScopeViolation extends \RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}

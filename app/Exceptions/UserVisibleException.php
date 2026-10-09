<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class UserVisibleException extends RuntimeException
{
    private ?string $field;
    private int $status;

    public function __construct(
        string $message,
        ?string $field = null,
        int $status = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->field = $field;
        $this->status = $status;
    }

    public static function forField(string $field, string $message, int $status = 422): self
    {
        return new self($message, $field, $status);
    }

    public function field(): ?string
    {
        return $this->field;
    }

    public function status(): int
    {
        return $this->status;
    }
}


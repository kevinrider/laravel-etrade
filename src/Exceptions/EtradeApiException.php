<?php

namespace KevinRider\LaravelEtrade\Exceptions;

class EtradeApiException extends \Exception
{
    private ?int $statusCode;
    private array $headers;
    private ?string $body;
    private ?string $method;
    private ?string $uri;

    public function __construct(
        string $message,
        ?int $statusCode = null,
        array $headers = [],
        ?string $body = null,
        ?string $method = null,
        ?string $uri = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);

        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->method = $method;
        $this->uri = $uri;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }
}

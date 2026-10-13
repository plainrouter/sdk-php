<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** @internal Records one generated copy call without altering responses, options, or failures. */
final class CopyResponseClient implements ClientInterface
{
    public ?ResponseInterface $response = null;

    public ?RequestException $failure = null;

    public function __construct(private readonly ClientInterface $client) {}

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->record(fn (): ResponseInterface => $this->client->send($request, $options));
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return $this->recordAsync($this->client->sendAsync($request, $options));
    }

    public function request(string $method, mixed $uri, array $options = []): ResponseInterface
    {
        return $this->record(fn (): ResponseInterface => $this->client->request($method, $uri, $options));
    }

    public function requestAsync(string $method, mixed $uri, array $options = []): PromiseInterface
    {
        return $this->recordAsync($this->client->requestAsync($method, $uri, $options));
    }

    public function getConfig(?string $option = null): mixed
    {
        return $this->client->getConfig($option);
    }

    /** @param callable(): ResponseInterface $send */
    private function record(callable $send): ResponseInterface
    {
        try {
            return $this->response = $send();
        } catch (RequestException $error) {
            $this->failure = $error;
            $this->response = $error->getResponse();
            throw $error;
        }
    }

    private function recordAsync(PromiseInterface $promise): PromiseInterface
    {
        return $promise->then(
            fn (ResponseInterface $response): ResponseInterface => $this->response = $response,
            function (mixed $error): RejectedPromise {
                if ($error instanceof RequestException) {
                    $this->failure = $error;
                    $this->response = $error->getResponse();
                }
                return new RejectedPromise($error);
            },
        );
    }
}

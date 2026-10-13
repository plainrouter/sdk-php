<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Plainrouter\OpenAPI\ApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RetryExamplesTest extends TestCase
{
    public static function retryHeaders(): iterable
    {
        yield 'absent' => [[], null];
        foreach (['Retry-After', 'retry-after'] as $name) {
            foreach (['1', '0', 'Wed, 14 Oct 2026 12:00:00 GMT'] as $value) {
                yield $name.' '.$value => [[$name => [$value]], $value];
            }
        }
    }

    #[DataProvider('retryHeaders')]
    public function test_documented_examples_agree_on_case_and_absence(array $headers, ?string $expected): void
    {
        $body = '{"error":{"code":"workspace_lock_timeout","message":"Retry."}}';
        $response = new Response(503, $headers, $body);
        $exception = new ApiException('Retry', 503, $headers, $body);
        $examples = [];
        foreach (['README.md', 'CHANGELOG.md'] as $file) {
            preg_match_all('/```php\n(.*?)```/s', file_get_contents(__DIR__.'/../'.$file), $blocks);
            foreach ($blocks[1] as $code) {
                if (str_contains($code, '$retryAfter =')) {
                    $examples[] = $code;
                }
            }
        }
        $this->assertNotEmpty($examples);
        foreach ($examples as $example) {
            $result = (static function (string $code, Response $response, ApiException $exception): array {
                eval($code);
                return [$retryable, $errorCode, $retryAfter];
            })($example, $response, $exception);
            $this->assertSame([true, 'workspace_lock_timeout', $expected], $result);
        }
    }
}

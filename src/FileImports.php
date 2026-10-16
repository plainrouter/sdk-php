<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\Promise\PromiseInterface;
use Plainrouter\IntegerInputApis\GuardedDraftBatchFileImportApi;
use Plainrouter\OpenAPI\Model\ApiRouteNotFound;

/** Decodes the documented workspace 404 on the curated file-import client. */
final class FileImports extends GuardedDraftBatchFileImportApi
{
    private const ERROR_MODELS = [
        'api_route_not_found' => ApiRouteNotFound::class,
    ];

    public function launcherFileImportWithHttpInfo($file, $source_adset_id, $source_ad_id, $mode, $folder_to_adset = null, $grouping_enabled = null, $account_id = null, $workspace_id = null, ?int $hostIndex = null, array $variables = [], string $contentType = self::contentTypes['launcherFileImport'][0]): array
    {
        [$observer, $generated] = $this->fileImportApi();
        return ErrorResponses::call(
            $observer,
            fn (): array => $generated->launcherFileImportWithHttpInfo($file, $source_adset_id, $source_ad_id, $mode, $folder_to_adset, $grouping_enabled, $account_id, $workspace_id, $hostIndex, $variables, $contentType),
            self::ERROR_MODELS,
        );
    }

    public function launcherFileImportAsyncWithHttpInfo($file, $source_adset_id, $source_ad_id, $mode, $folder_to_adset = null, $grouping_enabled = null, $account_id = null, $workspace_id = null, ?int $hostIndex = null, array $variables = [], string $contentType = self::contentTypes['launcherFileImport'][0]): PromiseInterface
    {
        [$observer, $generated] = $this->fileImportApi();
        return ErrorResponses::promise(
            $observer,
            $generated->launcherFileImportAsyncWithHttpInfo($file, $source_adset_id, $source_ad_id, $mode, $folder_to_adset, $grouping_enabled, $account_id, $workspace_id, $hostIndex, $variables, $contentType),
            self::ERROR_MODELS,
        );
    }

    /** @return array{CopyResponseClient, GuardedDraftBatchFileImportApi} */
    private function fileImportApi(): array
    {
        $observer = new CopyResponseClient($this->client);
        return [$observer, new GuardedDraftBatchFileImportApi($observer, $this->config, $this->headerSelector, $this->hostIndex)];
    }
}

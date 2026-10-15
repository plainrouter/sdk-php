<?php

declare(strict_types=1);

namespace Plainrouter\IntegerInputApis;

use Plainrouter\IntegerInputGuard;

/** Generated from spec/openapi.json by scripts/generate-php-integer-apis.mjs. */
class GuardedDraftBatchFileImportApi extends \Plainrouter\OpenAPI\Api\DraftBatchFileImportApi
{
    public function launcherFileImportRequest($file, $source_adset_id, $source_ad_id, $mode, $folder_to_adset = null, $grouping_enabled = null, $account_id = null, $workspace_id = null, ?int $hostIndex = null, array $variables = [], string $contentType = self::contentTypes['launcherFileImport'][0])
    {
        IntegerInputGuard::assertArguments('POST /mcp/launcher/import', self::class, __FUNCTION__, func_get_args(), null, [['account_id', 'account_id'], ['workspace_id', 'workspace_id']]);
        return parent::launcherFileImportRequest(...func_get_args());
    }

    protected function getHostSettingsForlauncherFileImport(): array
    {
        if ($this->config->getHost() !== \Plainrouter\Client::DEFAULT_BASE_URL) {
            return [['url' => $this->config->getHost(), 'description' => 'Configured base URL']];
        }
        return parent::getHostSettingsForlauncherFileImport();
    }
}

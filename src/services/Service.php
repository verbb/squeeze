<?php
namespace verbb\squeeze\services;

use verbb\squeeze\Squeeze;
use verbb\squeeze\models\Settings;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\helpers\Json;

use yii\web\ForbiddenHttpException;

use Exception;
use ZipArchive;

class Service extends Component
{
    // Constants
    // =========================================================================

    private const TOKEN_CONTEXT = 'verbb/squeeze:download-token:v1';
    private const TOKEN_PURPOSE = 'download';
    private const TOKEN_VERSION = 1;


    // Public Methods
    // =========================================================================

    /**
     * @param int[] $files
     * @param int[]|null $tokenFileIds When set, only these IDs are authorized by token.
     * @throws ForbiddenHttpException
     */
    public function archive(string $filename, array $files, ?array $tokenFileIds = null): string
    {
        $filename = FileHelper::sanitizeFilename($filename) ?: 'archive';
        $files = array_values(array_filter(array_map('intval', $files)));

        if (!$files) {
            throw new ForbiddenHttpException('No assets specified.');
        }

        if ($tokenFileIds !== null) {
            $tokenFileIds = array_values(array_filter(array_map('intval', $tokenFileIds)));
            $files = array_values(array_intersect($files, $tokenFileIds));

            if (!$files) {
                throw new ForbiddenHttpException('No assets specified.');
            }
        }

        $tokenAuthorized = $tokenFileIds !== null;
        $assets = Asset::find()->id($files)->limit(null)->all();
        $assetsById = [];

        foreach ($assets as $asset) {
            $assetsById[$asset->id] = $asset;
        }

        $authorizedAssets = [];

        foreach ($files as $fileId) {
            $asset = $assetsById[$fileId] ?? null;

            // Skip unknown/disabled IDs rather than revealing whether they exist.
            if (!$asset) {
                continue;
            }

            if (!$this->canDownloadAsset($asset, $tokenAuthorized)) {
                throw new ForbiddenHttpException('You are not permitted to download one or more of the requested assets.');
            }

            $authorizedAssets[] = $asset;
        }

        if (!$authorizedAssets) {
            throw new ForbiddenHttpException('No downloadable assets found.');
        }

        $tempFile = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . $filename . '_' . time() . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($tempFile, ZipArchive::CREATE) === true) {
            foreach ($authorizedAssets as $asset) {
                $zip->addFromString($asset->filename, $asset->getContents());
            }

            $zip->close();

            return $tempFile;
        }

        throw new Exception(Craft::t('squeeze', 'Failed to generate the archive'));
    }

    public function canDownloadAsset(Asset $asset, bool $tokenAuthorized = false): bool
    {
        /* @var Settings $settings */
        $settings = Squeeze::$plugin->getSettings();

        if (!$this->isVolumeAllowed($asset, $settings)) {
            return false;
        }

        // A valid signed token authorizes the specific asset IDs it was minted for.
        if ($tokenAuthorized) {
            return true;
        }

        $user = Craft::$app->getUser()->getIdentity();

        return $user && $asset->canView($user);
    }

    /**
     * @param int[]|Asset[] $files Asset IDs or Asset elements.
     */
    public function createToken(array $files, string $archiveName = 'archive', ?int $duration = null): string
    {
        /* @var Settings $settings */
        $settings = Squeeze::$plugin->getSettings();

        $files = $this->normalizeFileIds($files);
        $archiveName = FileHelper::sanitizeFilename($archiveName) ?: 'archive';

        if ($duration === null) {
            $duration = $settings->defaultTokenDuration;
        }

        $payload = [
            'version' => self::TOKEN_VERSION,
            'purpose' => self::TOKEN_PURPOSE,
            'files' => $files,
            'archivename' => $archiveName,
            'expires' => $duration ? time() + $duration : null,
        ];

        return Craft::$app->getSecurity()->hashData(Json::encode($payload), $this->_getTokenKey());
    }

    /**
     * @return array{files: int[], archivename: string}|null
     */
    public function validateToken(string $token): ?array
    {
        $data = Craft::$app->getSecurity()->validateData($token, $this->_getTokenKey());

        if ($data === false) {
            return null;
        }

        $payload = Json::decodeIfJson($data);

        if (!is_array($payload)
            || ($payload['version'] ?? null) !== self::TOKEN_VERSION
            || ($payload['purpose'] ?? null) !== self::TOKEN_PURPOSE
            || !isset($payload['files'])
            || !is_array($payload['files'])
            || !array_is_list($payload['files'])
            || $payload['files'] === []
            || !isset($payload['archivename'])
            || !is_string($payload['archivename'])
            || !array_key_exists('expires', $payload)
            || ($payload['expires'] !== null && !is_int($payload['expires']))) {
            return null;
        }

        foreach ($payload['files'] as $fileId) {
            if (!is_int($fileId) || $fileId <= 0) {
                return null;
            }
        }

        if (count(array_unique($payload['files'])) !== count($payload['files'])) {
            return null;
        }

        $archiveName = FileHelper::sanitizeFilename($payload['archivename']) ?: 'archive';

        if ($archiveName !== $payload['archivename']) {
            return null;
        }

        if ($payload['expires'] !== null && $payload['expires'] < time()) {
            return null;
        }

        return [
            'files' => $payload['files'],
            'archivename' => $archiveName,
        ];
    }


    // Private Methods
    // =========================================================================

    private function _getTokenKey(): string
    {
        $security = Craft::$app->getSecurity();
        $securityKey = Craft::$app->getConfig()->getGeneral()->securityKey;

        return $security->hkdf('sha256', $securityKey, null, self::TOKEN_CONTEXT, 32);
    }

    private function isVolumeAllowed(Asset $asset, Settings $settings): bool
    {
        if ($settings->allowedVolumes === '*' || $settings->allowedVolumes === null || $settings->allowedVolumes === []) {
            return true;
        }

        $volume = $asset->getVolume();
        $allowed = array_map('strval', (array)$settings->allowedVolumes);

        return in_array((string)$volume->id, $allowed, true)
            || in_array($volume->handle, $allowed, true)
            || in_array($volume->uid, $allowed, true);
    }

    /**
     * @param int[]|Asset[] $files
     * @return int[]
     */
    private function normalizeFileIds(array $files): array
    {
        $ids = [];

        foreach ($files as $file) {
            if ($file instanceof Asset) {
                $ids[] = (int)$file->id;
            } else {
                $ids[] = (int)$file;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}

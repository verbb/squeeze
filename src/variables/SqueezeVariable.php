<?php
namespace verbb\squeeze\variables;

use verbb\squeeze\Squeeze;

use craft\elements\Asset;
use craft\helpers\UrlHelper;

class SqueezeVariable
{
    // Public Methods
    // =========================================================================

    /**
     * Create a signed token for the given asset IDs or Asset elements.
     *
     * @param int[]|Asset[] $files
     */
    public function createToken(array $files, string $archiveName = 'archive', ?int $duration = null): string
    {
        return Squeeze::$plugin->getService()->createToken($files, $archiveName, $duration);
    }

    /**
     * Build a download URL that uses a signed token instead of raw asset IDs.
     *
     * @param int[]|Asset[] $files
     */
    public function getDownloadUrl(array $files, string $archiveName = 'archive', ?int $duration = null): string
    {
        $token = $this->createToken($files, $archiveName, $duration);

        return UrlHelper::actionUrl('squeeze/download', [
            'downloadToken' => $token,
        ]);
    }
}

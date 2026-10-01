<?php
namespace verbb\squeeze\controllers;

use verbb\squeeze\Squeeze;

use Craft;
use craft\helpers\FileHelper;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class DownloadController extends Controller
{
    // Properties
    // =========================================================================

    protected array|int|bool $allowAnonymous = true;


    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $token = $this->request->getParam('downloadToken');
        $tokenFileIds = null;

        if ($token) {
            $payload = Squeeze::$plugin->getService()->validateToken((string)$token);

            if ($payload === null) {
                throw new ForbiddenHttpException('Invalid or expired download token.');
            }

            $tokenFileIds = $payload['files'];
            $filename = $payload['archivename'];

            // Optional files[] subsets the token’s authorized IDs (e.g. checkbox UI).
            $files = $this->request->getParam('files', $tokenFileIds);

            if (!is_array($files)) {
                throw new BadRequestHttpException('Files must be an array of asset IDs.');
            }
        } else {
            // Raw files[] without a token requires a logged-in user who can view each asset.
            $files = $this->request->getRequiredParam('files');
            $filename = $this->request->getRequiredParam('archivename');

            if (!is_array($files)) {
                throw new BadRequestHttpException('Files must be an array of asset IDs.');
            }

            if (Craft::$app->getUser()->getIsGuest()) {
                throw new ForbiddenHttpException('A signed download token is required.');
            }
        }

        $archive = Squeeze::$plugin->getService()->archive((string)$filename, $files, $tokenFileIds);

        try {
            $response = Craft::$app->getResponse()->sendFile($archive, null, ['forceDownload' => true]);
            register_shutdown_function(static function() use ($archive, $response): void {
                self::_cleanupArchive($archive, $response);
            });
            $response->on(Response::EVENT_AFTER_SEND, static function() use ($archive, $response): void {
                self::_cleanupArchive($archive, $response);
            });

            return $response;
        } catch (\Throwable $e) {
            self::_cleanupArchive($archive);

            throw $e;
        }
    }


    // Private Methods
    // =========================================================================

    private static function _cleanupArchive(string $archive, ?Response $response = null): void
    {
        try {
            if ($response !== null) {
                $stream = is_array($response->stream) ? ($response->stream[0] ?? null) : $response->stream;

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $response->stream = null;
            }

            if (is_file($archive)) {
                FileHelper::unlink($archive);
            }

            FileHelper::removeDirectory(dirname($archive));
        } catch (\Throwable $e) {
            Craft::warning('Unable to remove a temporary Squeeze archive: ' . $e->getMessage(), __METHOD__);
        }
    }
}

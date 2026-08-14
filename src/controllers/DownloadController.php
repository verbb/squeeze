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

        $response = Craft::$app->getResponse()->sendFile($archive, null, ['forceDownload' => true]);

        FileHelper::unlink($archive);

        return $response;
    }
}

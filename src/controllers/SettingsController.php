<?php
namespace verbb\squeeze\controllers;

use verbb\squeeze\Squeeze;
use verbb\squeeze\models\Settings;

use Craft;
use craft\web\Controller;

use yii\web\Response;

class SettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        /* @var Settings $settings */
        $settings = Squeeze::$plugin->getSettings();

        $volumeOptions = [];

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $volumeOptions[] = [
                'label' => $volume->name,
                'value' => $volume->uid,
            ];
        }

        return $this->renderTemplate('squeeze/settings', [
            'settings' => $settings,
            'volumeOptions' => $volumeOptions,
        ]);
    }
}

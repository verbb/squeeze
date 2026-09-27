<?php
namespace verbb\squeeze\controllers;

use verbb\squeeze\Squeeze;
use verbb\squeeze\models\Settings;

use Craft;

use yii\web\Response;

use verbb\base\controllers\SettingsController as BaseSettingsController;

class SettingsController extends BaseSettingsController
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

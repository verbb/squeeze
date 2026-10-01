<?php
namespace verbb\squeeze\base;

use verbb\squeeze\Squeeze;
use verbb\squeeze\services\Service;

use Craft;

use verbb\base\LogTrait;
use verbb\base\helpers\Plugin;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static ?Squeeze $plugin = null;


    // Traits
    // =========================================================================

    use LogTrait;


    // Static Methods
    // =========================================================================

    public static function config(): array
    {
        Plugin::bootstrapPlugin('squeeze');

        return [
            'components' => [
                'service' => Service::class,
            ],
        ];
    }


    // Public Methods
    // =========================================================================

    public function getService(): Service
    {
        return $this->get('service');
    }

}

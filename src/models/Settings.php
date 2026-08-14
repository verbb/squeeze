<?php
namespace verbb\squeeze\models;

use craft\base\Model;

class Settings extends Model
{
    // Properties
    // =========================================================================

    /**
     * Volume UIDs, handles, or IDs that may be downloaded. `*` (or an empty list)
     * allows all volumes, subject to token or permission checks.
     *
     * @var string|string[]|int[]
     */
    public mixed $allowedVolumes = '*';

    /**
     * Default lifetime for signed download tokens, in seconds. Null means no expiry.
     */
    public ?int $defaultTokenDuration = 3600;


    // Public Methods
    // =========================================================================

    public function setAttributes($values, $safeOnly = true): void
    {
        if (array_key_exists('defaultTokenDuration', $values) && $values['defaultTokenDuration'] === '') {
            $values['defaultTokenDuration'] = null;
        }

        parent::setAttributes($values, $safeOnly);
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['defaultTokenDuration'], 'number', 'integerOnly' => true, 'min' => 1, 'skipOnEmpty' => true];

        return $rules;
    }
}

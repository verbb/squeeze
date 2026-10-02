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

    /**
     * Maximum number of assets in one archive. Null uses the 1,000-file safety limit.
     */
    public ?int $maxFiles = 100;

    /**
     * Maximum uncompressed archive size, in bytes. Null disables the limit.
     */
    public ?int $maxArchiveSize = 1073741824;


    // Public Methods
    // =========================================================================

    public function setAttributes($values, $safeOnly = true): void
    {
        foreach (['defaultTokenDuration', 'maxFiles', 'maxArchiveSize'] as $attribute) {
            if (array_key_exists($attribute, $values) && $values[$attribute] === '') {
                $values[$attribute] = null;
            }
        }

        parent::setAttributes($values, $safeOnly);
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['defaultTokenDuration', 'maxFiles', 'maxArchiveSize'], 'number', 'integerOnly' => true, 'min' => 1, 'skipOnEmpty' => true];

        return $rules;
    }
}

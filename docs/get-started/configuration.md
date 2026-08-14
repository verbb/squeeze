# Configuration

Create a `config/squeeze.php` file to override the default settings.

```php
<?php

return [
    // Only allow downloads from these volumes (UIDs, handles, or IDs). `*` = all volumes.
    'allowedVolumes' => [
        'images',
        'downloads',
    ],

    // Default signed token lifetime in seconds. Set to null for non-expiring tokens.
    'defaultTokenDuration' => 3600,
];
```

You can also manage these from **Settings → Plugins → Squeeze**.

Anonymous downloads always require a signed token. There is no setting to re-enable raw asset ID downloads for guests.

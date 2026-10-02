<?php

return [
    // Only allow downloads from these volumes (UIDs, handles, or IDs). `*` = all volumes.
    'allowedVolumes' => '*',

    // Default signed token lifetime in seconds. Set to null for non-expiring tokens.
    'defaultTokenDuration' => 3600,

    // Maximum number of assets in one archive. Set to null to use the 1,000-file safety limit.
    'maxFiles' => 100,

    // Maximum uncompressed archive size, in bytes. Set to null to disable the limit.
    'maxArchiveSize' => 1073741824,
];

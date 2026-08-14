<?php

return [
    // Only allow downloads from these volumes (UIDs, handles, or IDs). `*` = all volumes.
    'allowedVolumes' => '*',

    // Default signed token lifetime in seconds. Set to null for non-expiring tokens.
    'defaultTokenDuration' => 3600,
];

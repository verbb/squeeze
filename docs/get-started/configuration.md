# Configuration

You can customise Squeeze’s settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `squeeze.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will restrict downloads to a volume whose handle is `downloads`:

```php
<?php

return [
    'allowedVolumes' => ['downloads'],
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults. You can also manage these settings in the control panel under **Settings → Squeeze**.

## Configuration Options

::: reference
### `allowedVolumes`

**Type:** `string|array` · **Default:** `'*'`

The volumes permitted for downloads, identified by UID, handle or ID. An asterisk or an empty array permits all volumes, subject to token and permission checks. The opening example assumes a volume with the handle `downloads` exists. Anonymous downloads still require a signed token, so allowing a volume doesn’t give anonymous visitors access through raw asset IDs.
:::

::: reference
### `defaultTokenDuration`

**Type:** `int|null` · **Default:** `3600`

The lifetime of signed download tokens in seconds. The default is one hour. Set it to `null` only when the download link should have no expiry.
:::

::: reference
### `maxFiles`

**Type:** `int|null` · **Default:** `100`

The maximum number of assets Squeeze will include in one archive. Set it to `null` to use the 1,000-file safety limit.
:::

::: reference
### `maxArchiveSize`

**Type:** `int|null` · **Default:** `1073741824`

The maximum combined uncompressed size of the assets in one archive, in bytes. The default is 1 GiB. Set it to `null` to disable the size limit.
:::

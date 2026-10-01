# Configuration

You can customise Squeeze’s settings using a PHP configuration file. This is optional: each setting has a default, so only include the values you want to change.

Create `squeeze.php` in your Craft project's `/config` directory. For example, this restricts downloads to a volume whose handle is `downloads`:

```php
<?php

return [
    'allowedVolumes' => ['downloads'],
];
```

## Configuration Options

::: reference
### `allowedVolumes`

**Type:** `string|array` · **Default:** `'*'`

The volumes permitted for downloads, identified by UID, handle or ID. An asterisk or an empty array permits all volumes, subject to token and permission checks. The opening example assumes a volume with the handle `downloads` exists.
:::

::: reference
### `defaultTokenDuration`

**Type:** `int|null` · **Default:** `3600`

The lifetime of signed download tokens in seconds. The default is one hour. Set it to `null` only when the download link should have no expiry.
:::

::: reference
### `maxFiles`

**Type:** `int|null` · **Default:** `100`

The maximum number of assets Squeeze will include in one archive. Set it to `null` to allow any number of assets.
:::

::: reference
### `maxArchiveSize`

**Type:** `int|null` · **Default:** `1073741824`

The maximum combined uncompressed size of the assets in one archive, in bytes. The default is 1 GiB. Set it to `null` to disable the size limit.
:::

## Control Panel

You can also manage these settings from **Settings → Plugins → Squeeze**. Anonymous downloads require a signed token; allowing a volume does not give anonymous visitors access through raw asset IDs.

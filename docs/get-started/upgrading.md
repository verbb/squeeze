# Upgrading to signed downloads

Squeeze now requires authorization for downloads. This is a **security** change: anonymous requests can no longer download assets by guessing or iterating asset IDs.

While the [changelog](https://github.com/verbb/squeeze/blob/craft-5/CHANGELOG.md) lists every change, this guide covers what you need to update in your templates.

## Who is affected?

You need to update frontend templates if guests download files via Squeeze using raw asset IDs — for example:

```twig
<form method="post" target="_blank">
    {{ csrfInput() }}
    {{ actionInput('squeeze/download') }}
    {{ hiddenInput('archivename', 'archive') }}
    <input type="checkbox" name="files[]" value="10">
    <input type="checkbox" name="files[]" value="20">
    <input type="submit" value="Download!">
</form>
```

or a URL like:

```
/actions/squeeze/download?archivename=archive&files[]=10&files[]=20
```

Those requests will return **403** for guests after upgrading.

You do **not** need template changes if:

- Only logged-in users download, and they already have view permission on each asset
- You do not use Squeeze on the front end

## What to change

Mint a signed token in Twig for the assets you intend to expose, then pass that token instead of relying on raw IDs alone.

### Before

```twig
<form method="post" target="_blank">
    {{ csrfInput() }}
    {{ actionInput('squeeze/download') }}
    {{ hiddenInput('archivename', 'archive') }}

    {% for asset in assets %}
        <input type="checkbox" name="files[]" value="{{ asset.id }}">
    {% endfor %}

    <input type="submit" value="Download!">
</form>
```

### After

```twig
<form method="post" target="_blank">
    {{ csrfInput() }}
    {{ actionInput('squeeze/download') }}
    {{ hiddenInput('downloadToken', craft.squeeze.createToken(assets, 'archive')) }}

    {% for asset in assets %}
        <input type="checkbox" name="files[]" value="{{ asset.id }}">
    {% endfor %}

    <input type="submit" value="Download!">
</form>
```

`archivename` moves into `createToken()` (second argument). `createToken()` accepts an array of asset IDs or Asset elements. Optional `files[]` still subsets which of the token’s authorized assets are downloaded.

For a direct link with a fixed set of files:

```twig
<a href="{{ craft.squeeze.getDownloadUrl(assets, 'archive') }}">Download</a>
```

See [Usage](https://verbb.io/craft-plugins/squeeze/docs/feature-tour/usage) for more examples, including authenticated downloads without a token.

## Optional settings

After upgrading you can optionally restrict which volumes may be downloaded via **Settings → Plugins → Squeeze**, or in `config/squeeze.php`:

```php
<?php

return [
    'allowedVolumes' => [
        'images',
        'downloads',
    ],
    'defaultTokenDuration' => 3600,
];
```

Defaults keep all volumes allowed (`*`) and tokens expiring after one hour. No config change is required for downloads to work once templates mint tokens.

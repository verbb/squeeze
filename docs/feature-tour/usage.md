# Usage

Downloads must be authorized. Anonymous users need a **signed token**. Logged-in users may pass raw asset IDs only when they can view each asset.

## Signed tokens (required for anonymous downloads)

Mint a token in Twig for the assets you intend to expose. Pass either Asset elements or asset IDs:

```twig
{# Direct download link #}
<a href="{{ craft.squeeze.getDownloadUrl(assets, 'archive') }}">Download</a>

{# Form with a fixed set of files #}
<form method="post" target="_blank">
    {{ csrfInput() }}
    {{ actionInput('squeeze/download') }}
    {{ hiddenInput('downloadToken', craft.squeeze.createToken(assets, 'archive')) }}
    <input type="submit" value="Download!">
</form>
```

### Checkbox / subset selection

Mint a token for every asset shown on the page, then let the user choose a subset via `files[]`. Only IDs included in the token can be downloaded:

```twig
<form method="post" target="_blank">
    {{ csrfInput() }}
    {{ actionInput('squeeze/download') }}
    {{ hiddenInput('downloadToken', craft.squeeze.createToken(assets, 'archive')) }}

    {% for asset in assets %}
        <label>
            <input type="checkbox" name="files[]" value="{{ asset.id }}">
            {{ asset.title }}
        </label>
    {% endfor %}

    <input type="submit" value="Download!">
</form>
```

Tokens are signed with Craft’s security key and expire after one hour by default (`defaultTokenDuration` in config).

## Authenticated downloads

Logged-in users with view permission on the assets may still post raw IDs:

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

## Access control

For each asset Squeeze will:

1. Reject it when `allowedVolumes` is set (not `*`) and the asset’s volume is not listed
2. Allow it when the request includes a valid signed token that includes that asset ID
3. Otherwise allow it only when the current user can view the asset

# Usage

Squeeze packages selected assets into a ZIP download. For example, a Resources entry might have an Assets field with the handle `downloads`. Fetch those files in the entry's Twig template before using any of the examples below:

```twig
{% set assets = entry.downloads.all() %}
```

Select at least one file on the entry and save it. The examples use `archive` as the download name; replace it with a name meaningful to your visitors.

Downloads must be authorised. Anonymous users need a **signed token**. Logged-in users may pass raw asset IDs only when they can view each asset.

## Signed Tokens (Required for Anonymous Downloads)

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

### Checkbox / Subset Selection

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

Open the link as a logged-out visitor and inspect the ZIP: it should contain the assets selected on the entry. For the checkbox form, select a subset and check that only those files are included.

Tokens are signed with Craft’s security key and expire after one hour by default (`defaultTokenDuration` in [Configuration](docs:get-started/configuration)). If a previously working link expires, reload the page to obtain a fresh token. When caching a page containing a token, ensure the cache lifetime does not outlast the token.

## Authenticated Downloads

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

## Access Control

For each asset Squeeze will:

1. Reject it when `allowedVolumes` is set (not `*`) and the asset’s volume is not listed
2. Allow it when the request includes a valid signed token that includes that asset ID
3. Otherwise allow it only when the current user can view the asset

# WordPress.org Publish Checklist

This note is for publishing the selected `MHM Rentiva` visual assets to the WordPress.org plugin directory.

## Selected Assets

Local mirror inside this repo:

- `.wordpress-org/banner-1544x500.png`
- `.wordpress-org/banner-772x250.png`
- `.wordpress-org/icon-256x256.png`
- `.wordpress-org/icon-128x128.png`

Working copies kept for reference:

- `drafts/wporg-assets/banner-concept-3-with-icon-2.png`
- `drafts/wporg-assets/icon-concept-2-route-saas.png`

## Important Mapping

In this Git repo, `.wordpress-org/` is a local mirror only.

In the real WordPress.org SVN repository, these files must go to the top-level `/assets/` directory:

- `/assets/banner-1544x500.png`
- `/assets/banner-772x250.png`
- `/assets/icon-256x256.png`
- `/assets/icon-128x128.png`

Do not place these files under `/trunk/`.

## Pre-Publish Check

Before upload, confirm:

- `readme.txt` `Stable tag` matches the release version.
- The plugin code is ready for `/trunk/`.
- Asset filenames are lowercase.
- Banner sizes are exactly `1544x500` and `772x250`.
- Icon sizes are exactly `256x256` and `128x128`.
- No ZIP files are uploaded to SVN.

## Recommended SVN Flow

1. Check out the plugin SVN repository root.
2. Copy plugin code to `/trunk/`.
3. Copy these visual assets to `/assets/`.
4. If releasing a new version, copy `/trunk/` to `/tags/<version>/`.
5. Review `svn status` and `svn diff`.
6. Commit with a clear message.

Example shape:

```text
/assets/
/tags/4.27.6/
/trunk/
```

## Optional MIME Fix

If WordPress.org serves PNG files as downloads instead of images, run:

```bash
svn propset svn:mime-type image/png assets/*.png
```

## Source References

- Plugin assets handbook: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
- SVN workflow handbook: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

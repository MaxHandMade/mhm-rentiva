# MHM Rentiva WordPress.org Asset Workflow Guide

This guide explains the full end-to-end workflow we used for `MHM Rentiva` WordPress.org visual assets, from creative direction to final publish-ready files.

It is written for this repository and reflects the actual decisions already made for the plugin.

## 1. Goal

The goal is to prepare a clean and defensible visual asset set for the WordPress.org plugin directory:

- a banner for the plugin page header
- an icon for plugin search/list cards
- final files in WordPress.org-approved sizes
- a clear local-to-SVN handoff path

For `MHM Rentiva`, the visual system must communicate more than generic car rental.

The plugin positioning includes:

- vehicle rental
- transfer booking
- vendor marketplace support
- WooCommerce-powered booking/checkout

## 2. Official WordPress.org Constraints

Current official asset sizes:

- `banner-772x250.png`
- `banner-1544x500.png`
- `icon-128x128.png`
- `icon-256x256.png`

Official references:

- Plugin assets: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
- SVN workflow: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

Important publishing rule:

- In the real WordPress.org SVN repository, banners and icons belong in top-level `/assets/`
- Plugin code belongs in `/trunk/`
- Tagged releases go under `/tags/<version>/`

## 3. Messaging Strategy

The first critical step is not image generation. It is message definition.

For this plugin, a weak headline such as only `Car Rental Booking` would undersell the product.

The chosen message direction was:

- rental
- transfer
- vendor marketplace

That gives a more accurate market position and better differentiation.

The selected brand tone became:

- premium
- modern
- trustworthy
- WordPress plugin first, not just an automotive ad

## 4. Concept Exploration

We explored multiple banner directions before choosing the final style.

Banner directions:

- corporate premium
- modern SaaS / product-led
- bold vehicle-led marketing

Icon directions:

- corporate minimal
- route/SaaS system icon
- premium vehicle-led icon

This step matters because banner and icon must feel like the same product family.

## 5. Selected Creative Direction

Final selected direction:

- banner base: `banner-concept-3-with-icon-2.png`
- icon base: `icon-concept-2-route-saas.png`

Why this combination won:

- the banner is more memorable and visually stronger
- the selected icon explains rental + route/transfer + marketplace better than a plain automotive symbol
- together they feel like a real product identity, not disconnected stock assets

## 6. Working Files In This Repo

Concept exploration files:

- `drafts/wporg-assets/banner-concept-1-with-icon-2.png`
- `drafts/wporg-assets/banner-concept-3-with-icon-2.png`
- `drafts/wporg-assets/icon-concept-1-corporate-minimal.png`
- `drafts/wporg-assets/icon-concept-2-route-saas.png`
- `drafts/wporg-assets/icon-concept-3-premium-vehicle.png`

Prepared final-size files:

- `drafts/wporg-assets/final/banner-1544x500.png`
- `drafts/wporg-assets/final/banner-772x250.png`
- `drafts/wporg-assets/final/icon-256x256.png`
- `drafts/wporg-assets/final/icon-128x128.png`

Local mirror for WordPress.org asset placement:

- `.wordpress-org/banner-1544x500.png`
- `.wordpress-org/banner-772x250.png`
- `.wordpress-org/icon-256x256.png`
- `.wordpress-org/icon-128x128.png`

## 7. Local Repository Convention

This repo now uses two layers:

1. `drafts/wporg-assets/`
   Use this for ideation, alternates, and working notes.

2. `.wordpress-org/`
   Use this as the local mirror of the final WordPress.org asset set.

This separation is useful because:

- drafts stay available for future revisions
- final files stay easy to find
- the publishing path becomes obvious

## 8. Why `.wordpress-org/` Exists

WordPress.org SVN stores visual assets in `/assets/`, outside plugin code directories such as `/trunk/`.

This Git repo does not mirror the WordPress.org SVN structure exactly, so `.wordpress-org/` is used as a local, repo-friendly equivalent for final approved assets.

That means:

- `.wordpress-org/` is for local organization
- `/assets/` is the real destination in the WordPress.org SVN repo

To prevent mistakes, `.distignore` excludes `.wordpress-org/` from the release ZIP.

## 9. File Preparation Workflow

Once the final concept is selected, convert it into exact delivery sizes.

For this plugin, the chosen master banner and icon were resized into:

- `1544x500` retina banner
- `772x250` standard banner
- `256x256` large icon
- `128x128` standard icon

Rules followed during finalization:

- keep lowercase filenames
- keep exact WordPress.org asset names
- do not invent custom names for publish files
- verify real pixel dimensions after export
- keep drafts separate from finals

## 10. Quality Checks Before Publish

Before publishing, always verify:

- banner text is readable at smaller scale
- icon is recognizable at `128x128`
- no brand-infringing third-party car logos are visible
- no extra decorative text is present
- no watermarks or mockup chrome exist
- the visual style still matches the plugin positioning
- final files have the exact expected dimensions

For this repository, also verify:

- `readme.txt` stable tag matches the intended release
- plugin code in `/trunk/` is the version you want to publish
- `.wordpress-org/` is not mistakenly shipped in the install ZIP

## 11. Recommended Publish Flow

When the plugin release is ready:

1. Confirm the final asset set in `.wordpress-org/`
2. Check the plugin version and `readme.txt`
3. Prepare the WordPress.org SVN working copy
4. Copy plugin code to `/trunk/`
5. Copy visual assets to `/assets/`
6. If needed, copy `/trunk/` to `/tags/<version>/`
7. Review `svn status`
8. Review `svn diff`
9. Commit with a clear message

Expected SVN structure:

```text
/assets/
/trunk/
/tags/4.27.6/
```

## 12. Asset Mapping For Publish

Use this direct mapping:

Local repo:

- `.wordpress-org/banner-1544x500.png`
- `.wordpress-org/banner-772x250.png`
- `.wordpress-org/icon-256x256.png`
- `.wordpress-org/icon-128x128.png`

WordPress.org SVN destination:

- `/assets/banner-1544x500.png`
- `/assets/banner-772x250.png`
- `/assets/icon-256x256.png`
- `/assets/icon-128x128.png`

Do not place these under `/trunk/`.

## 13. Suggested Commit Message

If the visual assets are being published with a release, keep the message direct.

Examples:

- `Add WordPress.org banner and icon assets for MHM Rentiva`
- `Update WordPress.org assets for MHM Rentiva 4.27.6`

## 14. How To Revise Later

If you want a new round later, do not overwrite the exploration history immediately.

Recommended update flow:

1. create a new concept in `drafts/wporg-assets/`
2. review banner and icon together
3. approve one paired system
4. regenerate final sizes
5. replace only the files inside `.wordpress-org/`
6. publish updated files to WordPress.org SVN `/assets/`

## 15. Practical Summary

For this project, the safe and repeatable workflow is:

1. Define message first
2. Generate banner concepts
3. Generate icon concepts
4. Choose one consistent banner + icon pair
5. Export WordPress.org exact sizes
6. Store drafts in `drafts/wporg-assets/`
7. Store approved final files in `.wordpress-org/`
8. Publish those files to SVN `/assets/`

That is the complete working model for `MHM Rentiva` WordPress.org visual asset management.

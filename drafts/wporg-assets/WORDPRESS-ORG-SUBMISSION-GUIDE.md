# WordPress.org Submission Guide for MHM Rentiva

This document explains the full WordPress.org plugin submission process from start to finish.

It is written for `MHM Rentiva`, but the flow is also usable as a general WordPress.org publishing checklist.

Verified against official WordPress.org handbook pages on `April 24, 2026`.

## Official References

- Plugin Developer FAQ: https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/
- Detailed Plugin Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Planning, Submitting, and Maintaining Plugins: https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/
- Plugin Assets: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
- SVN Workflow: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

## 1. What WordPress.org Expects Before Submission

Before you submit anything, WordPress.org expects the plugin ZIP to be:

- a real installable `.zip`
- under `10 MB`
- production-ready
- complete
- free of unnecessary dev files, logs, placeholders, and incomplete code

The official FAQ says they do not accept placeholders or plugins that are not ready to be used.

The detailed guidelines also state that a complete plugin must be available at the time of submission, and names cannot be reserved for future use.

For `MHM Rentiva`, this means:

- no draft-only functionality in the release ZIP
- no dev tools that should stay local only
- no documentation or test folders that do not belong in the installable plugin
- all assets and compiled files must already exist

## 2. Current Repo-Specific Release Inputs

At the time of writing, this repo currently shows:

- plugin version in [`mhm-rentiva.php`](C:/projects/rentiva-dev/plugins/mhm-rentiva/mhm-rentiva.php:7): `4.30.0`
- stable tag in [`readme.txt`](C:/projects/rentiva-dev/plugins/mhm-rentiva/readme.txt:7): `4.30.0`

Release ZIP generation in this repo is documented in [`bin/BUILD.md`](C:/projects/rentiva-dev/plugins/mhm-rentiva/bin/BUILD.md:3) and uses:

```bash
cd plugins/mhm-rentiva
python bin/build-release.py
```

This is the correct local packaging path for this repository.

## 3. Stage 1: Pre-Submission Readiness

Before building the final ZIP, do this review.

### 3.1 Versioning

Make sure all release-facing version markers are aligned:

- plugin header version
- `readme.txt` stable tag
- changelog entry
- any internal release notes

WordPress.org guidelines say plugin version numbers must be incremented for each new release, and trunk `readme.txt` must reflect the current version.

### 3.2 Licensing

WordPress.org requires GPL-compatible licensing for all code, images, libraries, and bundled assets hosted in the directory.

For `MHM Rentiva`, confirm:

- bundled libraries are GPL-compatible
- bundled images are owned or licensed correctly
- no third-party asset is included with unclear usage rights

### 3.3 Naming and Trademark Safety

The official guidelines say plugin slugs must respect trademarks and project names.

Important practical rule:

- do not use another company or project name as the leading slug term unless you officially represent it

For this plugin, `mhm-rentiva` is original branding and is the correct direction.

### 3.4 Readme Quality

Before submission, check:

- plugin name is consistent
- short description is clear
- tags are relevant, not spammy
- installation instructions are real
- FAQ is accurate
- changelog is clean
- no keyword stuffing

Also validate the readme manually before submission.

### 3.5 Code Quality and Review Readiness

WordPress.org review is not only about syntax. They look for security, documentation, and presentation issues.

For this plugin, the practical pre-submit quality bar should include:

- Plugin Check clean enough for submission
- PHPCS clean under the project ruleset
- PHPUnit passing for the intended release
- no debug leftovers
- no accidental test data
- no unsafe remote execution patterns
- correct sanitization, escaping, nonce, and capability checks

## 4. Stage 2: Build the Installable ZIP

Build the plugin exactly as an end user would install it.

For this repo:

```bash
cd plugins/mhm-rentiva
python bin/build-release.py
```

This build flow is already designed to:

- read `.distignore`
- create a clean staging copy
- generate a WordPress-installable ZIP
- preserve POSIX paths inside the ZIP
- verify there is a single root plugin directory

That matters because WordPress.org wants a normal installable plugin ZIP, not a development archive.

## 5. Stage 3: Validate the ZIP Before Upload

Before submitting the ZIP on WordPress.org, do a final manual check:

- ZIP installs in WordPress normally
- plugin activates cleanly
- no fatal error on activation
- no obvious admin notices caused by missing build files
- no missing CSS/JS caused by excluded files
- no dev-only folders included

For `MHM Rentiva`, this is especially important because the repo contains local-only material such as:

- `docs/`
- `tests/`
- `bin/`
- `.worktrees/`
- local draft assets

These should not be in the submission ZIP.

## 6. Stage 4: Submit the Plugin

Submit from the official WordPress.org plugin submission page referenced by the FAQ.

At submission time:

1. Log into the correct WordPress.org account.
2. Upload the release ZIP.
3. Confirm the plugin name and slug direction are correct.
4. Submit only when the package is actually ready.

Important official rules from the FAQ:

- one submission at a time for most developers
- do not submit through multiple accounts to bypass limits
- do not submit unfinished plugins just to reserve a name

If the plugin is an official company plugin, submit it from the correct organization-controlled account, not from a random personal account that creates trademark confusion.

## 7. Stage 5: What Happens Immediately After Submission

After submission, WordPress.org sends an automated email right away.

That email typically tells you:

- your submission was received
- the review is queued
- the proposed plugin slug
- what to do if you made a submission mistake

The FAQ explains that the slug is derived from the main plugin name, and once approved it cannot be renamed freely. Treat the slug as a permanent decision.

For `MHM Rentiva`, the expected slug is likely `mhm-rentiva`, but the actual accepted slug is the one confirmed by the WordPress.org email.

## 8. Stage 6: Review Queue and Timing

As of the official FAQ page checked on `April 24, 2026`:

- there is no fixed average approval time
- a small, correct plugin can be approved within `14 days` of initial review
- the FAQ also says all plugins get an initial review within `4 weeks`
- replies to review follow-ups aim for `10 business days`

Do not plan launches assuming faster approval unless WordPress.org has explicitly told you so.

## 9. Stage 7: If Review Finds Problems

If the review team finds issues, they email back with details.

The right response is:

1. fix the issues in the plugin codebase
2. rebuild the release ZIP if needed
3. reply to the review email
4. do not create duplicate submissions unless the FAQ specifically says resubmission is required

The FAQ is explicit on this point:

- if you submitted with the wrong user ID, reply to the email, do not resubmit
- if you made a submission mistake, use the submission page or reply to the email
- if the review aged out after 3 months, then resubmit and reply so they know it continues the prior review

## 10. Stage 8: Approval and SVN Access

When approved, WordPress.org grants access to the plugin SVN repository.

At that point you are no longer uploading ZIPs for normal releases. Instead, you publish through SVN.

The official structure is:

```text
/assets/
/trunk/
/tags/
```

Official SVN placement rules:

- plugin code goes directly in `/trunk/`
- readme assets such as banner/icon files go in `/assets/`
- releases are copied into `/tags/<version>/`

Do not put the plugin code in a subdirectory under `/trunk/`. The FAQ says that breaks the zip generator.

## 11. Stage 9: First Live Publish After Approval

This is the first real WordPress.org publish after approval.

Recommended flow:

1. check out the SVN repository
2. copy release-ready plugin code into `/trunk/`
3. copy the approved banner and icon files into `/assets/`
4. copy `/trunk/` to `/tags/<version>/`
5. review `svn status`
6. review `svn diff`
7. commit with a clear message

For this repo, the local mirror of approved visual assets is:

- [banner-1544x500.png](C:/projects/rentiva-dev/plugins/mhm-rentiva/.wordpress-org/banner-1544x500.png)
- [banner-772x250.png](C:/projects/rentiva-dev/plugins/mhm-rentiva/.wordpress-org/banner-772x250.png)
- [icon-256x256.png](C:/projects/rentiva-dev/plugins/mhm-rentiva/.wordpress-org/icon-256x256.png)
- [icon-128x128.png](C:/projects/rentiva-dev/plugins/mhm-rentiva/.wordpress-org/icon-128x128.png)

These are local source files only.

In the real SVN repo they must be copied to:

- `/assets/banner-1544x500.png`
- `/assets/banner-772x250.png`
- `/assets/icon-256x256.png`
- `/assets/icon-128x128.png`

## 12. Stage 10: After the Plugin Goes Live

Once the first SVN commit is processed:

- the plugin page becomes public
- users can install from the WordPress.org directory
- later releases should be made by updating version numbers and committing a new `/trunk/` plus a matching `/tags/<version>/`

The detailed guidelines note that SVN is a release repository, not a development repository.

That means:

- do not spam tiny commits
- do not use meaningless commit messages
- only commit deployable states

## 13. Common Mistakes To Avoid

The most common submission mistakes are usually these:

- submitting too early
- trying to reserve a plugin name with an incomplete package
- including docs, tests, node/vendor junk, or logs in the ZIP
- submitting from the wrong WordPress.org account
- using a risky trademarked name
- forgetting to align version numbers
- shipping a ZIP that installs badly
- pushing assets to `/trunk/` instead of `/assets/`
- treating SVN like a development branch instead of a release channel

## 14. Practical MHM Rentiva Checklist

Use this project-specific checklist before submission:

1. Confirm `mhm-rentiva.php` version and `readme.txt` stable tag match.
2. Confirm changelog is ready.
3. Run the project verification steps you trust for release readiness.
4. Build with `python bin/build-release.py`.
5. Install and activate the ZIP on a clean WordPress instance.
6. Validate the final icon/banner set in `.wordpress-org/`.
7. Submit the ZIP on WordPress.org.
8. Watch for the automated slug email.
9. Respond to review feedback only through the existing review thread/email path.
10. After approval, publish code to SVN `/trunk/` and assets to SVN `/assets/`.

## 15. Short Version

If you want the shortest accurate model, it is this:

1. Make the plugin truly production-ready.
2. Build a clean installable ZIP under `10 MB`.
3. Submit it through WordPress.org.
4. Wait for review email and fix anything they flag.
5. After approval, publish code with SVN to `/trunk/` and `/tags/`.
6. Publish banner/icon assets to SVN `/assets/`.

That is the real start-to-finish WordPress.org submission process for `MHM Rentiva`.

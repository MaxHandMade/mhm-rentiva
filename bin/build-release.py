#!/usr/bin/env python3
"""
Build a WordPress-installable release ZIP for MHM Rentiva.

Produces: build/mhm-rentiva.<version>.zip

Usage:
    python bin/build-release.py

What it does:
    1. Reads the version from mhm-rentiva.php
    2. Reads .distignore for the exclusion list
    3. Stages a clean copy of the plugin under build/zip-staging/mhm-rentiva/
    4. Creates build/mhm-rentiva.<version>.zip with POSIX (forward-slash) paths
       so WordPress's built-in unzip_file() and every other unzip tool accepts it

Why a separate script (and not Compress-Archive):
    PowerShell's Compress-Archive writes ZIPs with backslash path separators on
    Windows. WordPress core's unzip_file() tolerates them but prints a warning,
    and some hosting panels/plugins reject them outright. Python's zipfile
    always writes POSIX paths, which is the WordPress.org-recommended format.

No external dependencies — stdlib only (Python 3.8+).
"""
from __future__ import annotations

import fnmatch
import os
import re
import shutil
import sys
import zipfile
from pathlib import Path

PLUGIN_SLUG = "mhm-rentiva"
ROOT = Path(__file__).resolve().parent.parent
BUILD_DIR = ROOT / "build"
STAGING_DIR = BUILD_DIR / "zip-staging" / PLUGIN_SLUG
DISTIGNORE = ROOT / ".distignore"
MAIN_PLUGIN_FILE = ROOT / f"{PLUGIN_SLUG}.php"

# The constant was renamed to MHMRENTIVA_VERSION by the 6.0.0 prefix sweep.
# Left unfixed, read_version() finds nothing and the release build exits --
# at ZIP time, which is the worst place to discover a rename.
VERSION_RE = re.compile(r"define\(\s*'MHMRENTIVA_VERSION'\s*,\s*'([^']+)'\s*\)\s*;")


def read_version() -> str:
    text = MAIN_PLUGIN_FILE.read_text(encoding="utf-8")
    match = VERSION_RE.search(text)
    if not match:
        sys.exit(f"ERROR: could not find MHMRENTIVA_VERSION in {MAIN_PLUGIN_FILE}")
    return match.group(1)


def load_distignore() -> list[tuple[bool, str]]:
    """Return (negate, pattern) tuples from .distignore, in file order.

    A leading "!" negates the pattern (re-includes a path an earlier pattern
    excluded) — .gitignore semantics. Order matters: the LAST matching
    pattern in the file wins, so a negation must appear after the pattern
    it re-includes.
    """
    if not DISTIGNORE.exists():
        return []
    patterns: list[tuple[bool, str]] = []
    for raw in DISTIGNORE.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        negate = line.startswith("!")
        body = line[1:] if negate else line
        body = body.rstrip("/")
        if not body:
            continue
        patterns.append((negate, body))
    return patterns


def _pattern_matches(rel_path: str, pat: str) -> bool:
    """Match a POSIX-style relative path against a single pattern body
    (the "!" negation prefix, if any, has already been stripped by the
    caller — this only decides whether `pat` matches `rel_path`).

    A pattern matches if it equals the path, is a prefix directory of the
    path, or matches any path component via glob (e.g. '*.zip',
    'languages/*~').

    A leading "/" anchors the pattern to the plugin root (.gitignore
    semantics). An anchored pattern may itself contain glob characters
    (e.g. "/vendor/*" matches every direct child of vendor/ AND, because
    fnmatch's "*" also matches "/", every path nested under it) — this is
    what lets ".distignore" exclude "/vendor/*" wholesale while a later
    "!/vendor/mhm/" negation re-includes just that subtree.

    Without a leading "/", a plain name matches on any path component — so
    "vendor" excludes both "/vendor/" (Composer) AND "/assets/js/vendor/".
    Use "/vendor/" when you only want to exclude the root Composer dir.
    """
    if pat.startswith("/"):
        anchored = pat[1:]
        if not anchored:
            return False
        if "*" in anchored or "?" in anchored or "[" in anchored:
            if fnmatch.fnmatch(rel_path, anchored):
                return True
        return rel_path == anchored or rel_path.startswith(anchored + "/")
    if "/" in pat or "*" in pat or "?" in pat:
        # Glob / path pattern: test against full path and each suffix
        if fnmatch.fnmatch(rel_path, pat):
            return True
        # Directory-prefix match: "docs/" should exclude "docs/foo"
        return rel_path == pat or rel_path.startswith(pat + "/")
    # Plain name: match on any path component (e.g. '.git', 'node_modules')
    return pat in rel_path.split("/")


def is_excluded(rel_path: str, patterns: list[tuple[bool, str]]) -> bool:
    """Decide whether a relative path should be left out of the release ZIP.

    Evaluates every pattern in file order and keeps the verdict of the LAST
    one that matches (.gitignore "last match wins" semantics), so a
    negation pattern ("!...") placed after an exclusion can re-include a
    path that would otherwise be dropped.
    """
    excluded = False
    for negate, pat in patterns:
        if _pattern_matches(rel_path, pat):
            excluded = not negate
    return excluded


def stage_files(patterns: list[tuple[bool, str]]) -> int:
    if BUILD_DIR.exists():
        # Only clear the staging subdir, keep older ZIPs in build/
        if STAGING_DIR.parent.exists():
            shutil.rmtree(STAGING_DIR.parent, ignore_errors=True)
    STAGING_DIR.mkdir(parents=True, exist_ok=True)

    copied = 0
    for root, dirs, files in os.walk(ROOT):
        root_path = Path(root)
        # Compute path relative to plugin root with POSIX separators
        try:
            rel_root = root_path.relative_to(ROOT).as_posix()
        except ValueError:
            continue
        if rel_root == ".":
            rel_root = ""

        # Prune excluded directories in-place so os.walk does not descend into them
        pruned: list[str] = []
        for d in dirs:
            rel_d = f"{rel_root}/{d}" if rel_root else d
            if is_excluded(rel_d, patterns):
                continue
            pruned.append(d)
        dirs[:] = pruned

        for f in files:
            rel_f = f"{rel_root}/{f}" if rel_root else f
            if is_excluded(rel_f, patterns):
                continue
            src = root_path / f
            dest = STAGING_DIR / rel_f
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(src, dest)
            copied += 1
    return copied


def inject_l10n_abspath_guard() -> int:
    """Prepend ABSPATH guard to staged `languages/*.l10n.php` files.

    Why this exists:
        `wp i18n make-php` emits files in the form `<?php\nreturn [...];` with
        no direct-file-access guard. WordPress.org Plugin Check flags this as
        an ERROR (`missing_direct_file_access_protection`) and blocks
        submission. We patch only the *staged* copies so the source files in
        `languages/` stay in WP-CLI's canonical format (no diff churn when
        i18n is regenerated).
    """
    guard       = "if ( ! defined( 'ABSPATH' ) ) { exit; }"
    patched     = 0
    languages   = STAGING_DIR / "languages"
    if not languages.exists():
        return 0
    for path in languages.glob("*.l10n.php"):
        # newline='' disables Python's universal-newline translation. Without
        # it, write_text() on Windows rewrites every '\n' to '\r\n' — including
        # '\n' characters embedded *inside* multi-line translated string
        # values (e.g. a msgid/msgstr pair like "Line one\nLine two"), silently
        # corrupting those keys so WordPress's runtime __() lookup (which uses
        # the untouched '\n'-only PHP source string) never matches them.
        original = path.read_text(encoding="utf-8", newline="")
        # Skip if already protected (e.g. someone edited the source file).
        if "defined('ABSPATH')" in original.replace(" ", "")[:200]:
            continue
        if original.startswith("<?php\n"):
            new_content = "<?php\n" + guard + "\n" + original[6:]
        elif original.startswith("<?php"):
            new_content = "<?php\n" + guard + "\n" + original[5:]
        else:
            # Not a PHP file we recognize — leave it alone.
            continue
        path.write_text(new_content, encoding="utf-8", newline="")
        patched += 1
    return patched


def create_zip(version: str) -> Path:
    zip_path = BUILD_DIR / f"{PLUGIN_SLUG}.{version}.zip"
    if zip_path.exists():
        zip_path.unlink()

    count = 0
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for root, _dirs, files in os.walk(STAGING_DIR.parent):
            for f in files:
                full = Path(root) / f
                arcname = full.relative_to(STAGING_DIR.parent).as_posix()
                zf.write(full, arcname)
                count += 1
    return zip_path


def main() -> int:
    if not MAIN_PLUGIN_FILE.exists():
        sys.exit(f"ERROR: {MAIN_PLUGIN_FILE} not found")

    version = read_version()
    patterns = load_distignore()

    print(f"[build] Plugin   : {PLUGIN_SLUG}")
    print(f"[build] Version  : {version}")
    print(f"[build] Source   : {ROOT}")
    print(f"[build] Patterns : {len(patterns)} from .distignore")

    copied = stage_files(patterns)
    print(f"[build] Staged   : {copied} files -> {STAGING_DIR}")

    guarded = inject_l10n_abspath_guard()
    print(f"[build] Guarded  : {guarded} l10n.php file(s) (ABSPATH injected)")

    zip_path = create_zip(version)
    size_mb = zip_path.stat().st_size / (1024 * 1024)
    print(f"[build] SUCCESS  : {zip_path}")
    print(f"[build] Size     : {size_mb:.2f} MB")

    # Sanity-check: the ZIP must have exactly one top-level directory named
    # mhm-rentiva/ so WordPress installs it correctly.
    with zipfile.ZipFile(zip_path) as zf:
        roots = {name.split("/")[0] for name in zf.namelist()}
    if roots != {PLUGIN_SLUG}:
        sys.exit(f"ERROR: ZIP has unexpected top-level dirs: {sorted(roots)}")
    print(f"[build] Verified : single root '{PLUGIN_SLUG}/'")

    # Every file sitting directly in that root is named here. `.distignore` is
    # a deny-list, so anything it does not anticipate ships silently -- which is
    # how a scratch file (dead-catalogs.tmp) reached the 5.2.1 and 5.2.2
    # releases. An allow-list fails on the file nobody thought to exclude.
    allowed_root_files = {
        "LICENSE",
        "changelog-tr.json",
        "changelog.json",
        "composer.json",
        f"{PLUGIN_SLUG}.php",
        "readme.txt",
        "uninstall.php",
    }
    with zipfile.ZipFile(zip_path) as zf:
        root_files = {
            name.split("/", 1)[1]
            for name in zf.namelist()
            if name.count("/") == 1 and not name.endswith("/")
        }
    unexpected = sorted(root_files - allowed_root_files)
    if unexpected:
        sys.exit(
            "ERROR: unexpected file(s) in the ZIP root: "
            + ", ".join(unexpected)
            + "\n  Exclude them in .distignore, or add them to "
              "allowed_root_files in this script if they belong."
        )
    missing = sorted(allowed_root_files - root_files)
    if missing:
        sys.exit(f"ERROR: expected root file(s) absent from the ZIP: {missing}")
    print(f"[build] Verified : {len(root_files)} root files, all expected")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

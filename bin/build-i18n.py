#!/usr/bin/env python3
"""
Regenerate the deterministic, WordPress-resolvable i18n JSON set for MHM Rentiva.

Produces / refreshes: languages/mhm-rentiva-<locale>-<md5>.json

Usage:
    python bin/build-i18n.py [--build-js] [--locale tr_TR] [--container NAME]

What it does:
    1. (optional, --build-js) Runs `npm ci` + `npm run build` on the host so
       build/admin/*.js exist for every React admin entry.
    2. Runs `wp i18n make-json languages/<domain>-<locale>.po languages/ --no-purge`
       inside the WP-CLI container, **with cwd = plugin root**.
    3. Removes any stray non-canonical JSON (source path not plugin-root
       relative) — the historical garbage caused by running make-json from
       inconsistent working directories (build/zip-staging, wp-content, ...).
    4. Asserts the 9 React canonical files exist and that
       md5('build/admin/<page>.js') == the WP runtime-resolved filename.
       Fails loudly if the set is incomplete (the bug this script prevents).

Why this script exists (the bug it fixes):
    WordPress core's load_script_textdomain() resolves a script handle's
    translations to `<domain>-<locale>-md5(<src-relative-to-plugin-root>).json`.
    For the React admin pages the registered src is `build/admin/<page>.js`
    (see AssetManager::enqueue_react_page), so the ONLY file WP ever loads is
    `mhm-rentiva-<locale>-<md5('build/admin/<page>.js')>.json`.

    These files are generated artifacts. Historically they were:
      - never committed (`.gitignore: languages/*-*.json`), so a fresh clone
        shipped an untranslated React admin;
      - produced by an unnecessary `--use-map` + copy-to-handle-name recipe
        whose handle-named output WP never loads (vestigial);
      - accumulated as duplicates because make-json was run from 3 different
        working directories over time (plugin root, build/zip-staging,
        wp-content) → 3 different md5s for the same script.

    This script makes regeneration deterministic (always plugin-root cwd) and
    is paired with committing the canonical set (see .gitignore) so a clean
    clone is, by definition, reproducible.

No host dependencies beyond Docker + (with --build-js) Node/npm.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
import sys
from pathlib import Path

PLUGIN_SLUG = "mhm-rentiva"
DOMAIN = "mhm-rentiva"
ROOT = Path(__file__).resolve().parent.parent
LANGUAGES = ROOT / "languages"
BUILD_ADMIN = ROOT / "build" / "admin"
CONTAINER_PLUGIN_DIR = f"/var/www/html/wp-content/plugins/{PLUGIN_SLUG}"
DEFAULT_CONTAINER = "rentiva-dev-wpcli-1"


def run(cmd: list[str], *, cwd: Path | None = None) -> str:
    """Run a command, stream nothing, return stdout; abort on failure."""
    result = subprocess.run(
        cmd, cwd=str(cwd) if cwd else None,
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        sys.stderr.write(result.stdout)
        sys.stderr.write(result.stderr)
        sys.exit(f"ERROR: command failed ({result.returncode}): {' '.join(cmd)}")
    return result.stdout


def react_entries() -> list[str]:
    """React admin entry basenames, derived from build/admin/*.js (the
    webpack output). These map 1:1 to handle `mhm-rentiva-react-<entry>`
    and to WP's md5 basis `build/admin/<entry>.js`."""
    if not BUILD_ADMIN.exists():
        return []
    return sorted(p.stem for p in BUILD_ADMIN.glob("*.js"))


def canonical_react_filename(entry: str, locale: str) -> str:
    """The ONLY filename WordPress will load for handle mhm-rentiva-react-<entry>."""
    rel = f"build/admin/{entry}.js"
    return f"{DOMAIN}-{locale}-{hashlib.md5(rel.encode()).hexdigest()}.json"


def main() -> int:
    ap = argparse.ArgumentParser(description="Regenerate deterministic i18n JSON")
    ap.add_argument("--locale", default="tr_TR")
    ap.add_argument("--container", default=DEFAULT_CONTAINER,
                    help="WP-CLI Docker container name")
    ap.add_argument("--build-js", action="store_true",
                    help="Run npm ci + npm run build on the host first")
    args = ap.parse_args()

    locale = args.locale
    po_rel = f"languages/{DOMAIN}-{locale}.po"
    if not (ROOT / po_rel).exists():
        sys.exit(f"ERROR: {po_rel} not found (the committed translation source)")

    print(f"[i18n] Plugin    : {PLUGIN_SLUG}")
    print(f"[i18n] Locale    : {locale}")
    print(f"[i18n] Container : {args.container}")

    if args.build_js:
        print("[i18n] npm ci ...")
        run(["npm", "ci"], cwd=ROOT)
        print("[i18n] npm run build ...")
        run(["npm", "run", "build"], cwd=ROOT)

    entries = react_entries()
    if not entries:
        sys.exit("ERROR: build/admin/*.js not found — run with --build-js "
                 "or `npm run build` first (make-json needs the built bundles)")
    print(f"[i18n] React     : {len(entries)} entries ({', '.join(entries)})")

    # The proven canonical recipe: plain make-json from the committed .po,
    # WITH cwd = plugin root, so md5(source) matches WP's runtime resolution.
    print("[i18n] make-json (plugin-root cwd, no --use-map) ...")
    out = run([
        "docker", "exec", args.container, "bash", "-c",
        f"cd {CONTAINER_PLUGIN_DIR} && "
        f"wp i18n make-json {po_rel} languages/ --no-purge --allow-root",
    ])
    print("       " + out.strip().splitlines()[-1] if out.strip() else "")

    # Defensive cleanup: drop any JSON whose `source` is NOT a plugin-root
    # relative path (the historical staging/wp-content duplicates). A correct
    # plugin-root run never produces these; this guards against drift.
    removed = 0
    for jf in LANGUAGES.glob(f"{DOMAIN}-{locale}-*.json"):
        try:
            src = json.loads(jf.read_text(encoding="utf-8")).get("source", "")
        except Exception:
            continue
        if src and (src.startswith("build/zip-staging/")
                    or src.startswith("wp-content/")
                    or src.startswith("/")):
            jf.unlink()
            removed += 1
    print(f"[i18n] Cleaned   : {removed} stray non-canonical JSON")

    # Hard assertion: every React entry MUST have its canonical file with the
    # correct source, or the React admin ships untranslated (the original bug).
    missing: list[str] = []
    for entry in entries:
        fn = canonical_react_filename(entry, locale)
        path = LANGUAGES / fn
        if not path.exists():
            missing.append(f"{entry} -> {fn} (absent)")
            continue
        src = json.loads(path.read_text(encoding="utf-8")).get("source", "")
        if src != f"build/admin/{entry}.js":
            missing.append(f"{entry} -> {fn} (source={src!r})")
    if missing:
        sys.exit("ERROR: canonical React i18n set incomplete:\n  "
                 + "\n  ".join(missing))

    total = len(list(LANGUAGES.glob(f"{DOMAIN}-{locale}-*.json")))
    print(f"[i18n] Verified  : {len(entries)}/{len(entries)} React canonical "
          f"files OK; {total} total {locale} JSON")
    print("[i18n] SUCCESS   : commit languages/ — clean clone is now reproducible")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

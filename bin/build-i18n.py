#!/usr/bin/env python3
"""
Regenerate the deterministic, WordPress-resolvable i18n JSON set for MHM Rentiva.

Produces / refreshes: languages/mhm-rentiva-<locale>-<md5>.json

Usage:
    python bin/build-i18n.py [--build-js] [--locale tr_TR] [--container NAME]
    python bin/build-i18n.py --verify-only     # CI mode: no Docker, no Node

`--verify-only` runs step 5 alone. It reads the .po and languages/*.json and
nothing else, which is why CI can run it: the generating half needs WP-CLI in
Docker, but the checking half needs neither Docker nor Node nor the network.

What it does:
    1. (optional, --build-js) Runs `npm ci` + `npm run build` on the host so
       build/admin/*.js exist for every React admin entry.
    2. Writes .i18n-map.json — every src-react/**/*.{js,jsx} source mapped to
       the build/admin/<entry>.js bundle webpack compiles it into.
    3. Runs `wp i18n make-json languages/<domain>-<locale>.po languages/
       --no-purge --extensions=jsx --use-map=.i18n-map.json` inside the WP-CLI
       container, **with cwd = plugin root**.
    4. Removes any stray non-canonical JSON (source path not plugin-root
       relative) — the historical garbage caused by running make-json from
       inconsistent working directories (build/zip-staging, wp-content, ...).
    5. Asserts, per React entry, that md5('build/admin/<page>.js') names an
       existing file, that its `source` is that bundle, that this run actually
       rewrote it (translation-revision-date == the .po's PO-Revision-Date),
       and that it carries EVERY msgid the .po references from that bundle's
       sources. Fails loudly otherwise.

Why steps 2 and 5 are not optional (measured 2026-07-28):
    `make-json` searches ".min.js and .js extensions" by default and keys its
    output on md5(<reference path>). Every React reference in our .pot is a
    `src-react/**/*.jsx` path, so a bare run matches NOTHING for the React
    admin — it prints a success line and writes zero React files. Because we
    (correctly) pass --no-purge, the previously generated files survive
    untouched, so an existence-only assertion stays green forever. That is
    exactly what happened: all four React catalogs sat frozen at
    `2026-04-14 23:17+0000` while five already-translated strings
    ("Pending Payments", "Upcoming Operations", "No pending payments.",
    "No upcoming operations in the next 7 days.", "An error occurred. Please
    refresh the page.") shipped in English. The docblock below used to claim
    --use-map was "unnecessary"; it is required, and the assertion has to
    compare CONTENT, not existence, or it cannot see this failure at all.

Why this script exists (the bug it fixes):
    WordPress core's load_script_textdomain() resolves a script handle's
    translations to `<domain>-<locale>-md5(<src-relative-to-plugin-root>).json`.
    For the React admin pages the registered src is `build/admin/<page>.js`
    (see AssetManager::enqueue_react_page), so the ONLY file WP ever loads is
    `mhm-rentiva-<locale>-<md5('build/admin/<page>.js')>.json`.

    These files are generated artifacts. Historically they were:
      - never committed (`.gitignore: languages/*-*.json`), so a fresh clone
        shipped an untranslated React admin;
      - produced by a `--use-map` + copy-to-handle-name recipe whose
        handle-named half WP never loads (that copy step was vestigial — but
        the --use-map half was load-bearing and dropping it broke regeneration);
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
import re
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
MAP_FILE = ROOT / ".i18n-map.json"
# Jed/gettext context separator (EOT): how a msgctxt entry is keyed in the JSON.
CONTEXT_SEP = "\x04"


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


def source_to_bundles(entries: list[str]) -> dict[str, list[str]]:
    """Map every React source file to the bundle(s) webpack compiles it into.

    make-json keys its output on md5(<reference path>), and our .pot references
    are `src-react/**` source paths — never `build/admin/*.js`. Without this map
    the React half of the catalog cannot be generated at all (see module
    docblock). `src-react/shared/**` is reachable from every entry, so it maps
    to all of them; an extra key in a bundle catalog costs bytes, a missing one
    costs a silent English fallback.
    """
    mapping: dict[str, list[str]] = {}
    all_bundles = [f"build/admin/{e}.js" for e in entries]

    for entry in entries:
        entry_dir = ROOT / "src-react" / "admin" / entry
        for path in sorted(entry_dir.rglob("*")):
            if path.suffix in (".js", ".jsx"):
                mapping[path.relative_to(ROOT).as_posix()] = [f"build/admin/{entry}.js"]

    shared_dir = ROOT / "src-react" / "shared"
    for path in sorted(shared_dir.rglob("*")):
        if path.suffix in (".js", ".jsx"):
            mapping[path.relative_to(ROOT).as_posix()] = list(all_bundles)

    return mapping


def po_revision_date(po_path: Path) -> str:
    """The .po header's PO-Revision-Date, which make-json copies into every JSON
    it writes. Comparing it to the JSON is how we detect a file this run did not
    actually rewrite."""
    for line in po_path.read_text(encoding="utf-8").splitlines():
        if line.startswith('"PO-Revision-Date:'):
            return line.split("PO-Revision-Date:", 1)[1].rstrip('\\n"').strip()
        if line and not line.startswith(('"', "msgid", "msgstr", "#")):
            break
    return ""


def po_msgids_by_bundle(po_path: Path, entries: list[str]) -> dict[str, set[str]]:
    """Every Jed key the .po contributes to each bundle, derived from `#:` refs.

    Jed keys are `msgid`, or `msgctxt \\x04 msgid` when a context is present —
    the same shape make-json writes, so the two sets are directly comparable.
    """
    text = po_path.read_text(encoding="utf-8")
    by_bundle: dict[str, set[str]] = {e: set() for e in entries}

    def unquote(chunk: str) -> str:
        out = []
        for raw in re.findall(r'^(?:msgid|msgctxt|msgstr)?\s*"(.*)"$', chunk, re.M):
            out.append(raw.replace('\\"', '"').replace("\\\\", "\\").replace("\\n", "\n"))
        return "".join(out)

    for block in text.split("\n\n"):
        if block.lstrip().startswith("#~") or "\nmsgid " not in "\n" + block:
            continue
        refs = [r for line in re.findall(r"^#: (.+)$", block, re.M) for r in line.split()]
        if not refs:
            continue
        msgid_part = re.search(r'^msgid ((?:".*"\n?)+)', block, re.M)
        if not msgid_part:
            continue
        key = unquote(msgid_part.group(1))
        if not key:
            continue  # header entry
        ctxt_part = re.search(r'^msgctxt ((?:".*"\n?)+)', block, re.M)
        if ctxt_part:
            key = unquote(ctxt_part.group(1)) + CONTEXT_SEP + key

        for ref in refs:
            source = ref.rsplit(":", 1)[0]
            if source.startswith("src-react/admin/"):
                entry = source.split("/")[2]
                if entry in by_bundle:
                    by_bundle[entry].add(key)
            elif source.startswith("src-react/shared/"):
                for entry in by_bundle:
                    by_bundle[entry].add(key)

    return by_bundle


def verify_react_catalogs(
    entries: list[str], locale: str, po_path: Path
) -> tuple[list[str], str, int]:
    """Check every React canonical catalog against the .po it must mirror.

    Existence is NOT enough: --no-purge means a file this run failed to write
    survives from the previous one, so an existence-only check cannot tell
    "regenerated" from "four months stale" — the exact reason the old check
    stayed green for four months. Hence three checks per entry: the `source`
    field, freshness (revision-date must equal the .po's), and completeness
    (every msgid the .po references from this bundle's sources must be present).

    Returns (problems, po_revision_date, bundle_msgid_pairs_checked). An empty
    problem list is the only PASS.
    """
    want_by_bundle = po_msgids_by_bundle(po_path, entries)
    po_date = po_revision_date(po_path)
    if not po_date:
        return ([f"{po_path.name} has no PO-Revision-Date to verify against"], "", 0)

    problems: list[str] = []
    for entry in entries:
        fn = canonical_react_filename(entry, locale)
        path = LANGUAGES / fn
        if not path.exists():
            problems.append(f"{entry} -> {fn} (absent)")
            continue
        data = json.loads(path.read_text(encoding="utf-8"))
        src = data.get("source", "")
        if src != f"build/admin/{entry}.js":
            problems.append(f"{entry} -> {fn} (source={src!r})")
            continue
        json_date = (data.get("translation-revision-date") or "").strip()
        if json_date != po_date:
            problems.append(
                f"{entry} -> {fn} (STALE: revision-date {json_date!r} != "
                f".po {po_date!r} — make-json wrote nothing for this bundle)")
            continue
        have = set(data.get("locale_data", {}).get("messages", {}))
        absent = want_by_bundle[entry] - have
        if absent:
            sample = ", ".join(repr(m) for m in sorted(absent)[:5])
            problems.append(
                f"{entry} -> {fn} ({len(absent)} msgid(s) in the .po but not in "
                f"the catalog: {sample})")

    covered = sum(len(want_by_bundle[e]) for e in entries)
    return (problems, po_date, covered)


def main() -> int:
    ap = argparse.ArgumentParser(description="Regenerate deterministic i18n JSON")
    ap.add_argument("--locale", default="tr_TR")
    ap.add_argument("--container", default=DEFAULT_CONTAINER,
                    help="WP-CLI Docker container name")
    ap.add_argument("--build-js", action="store_true",
                    help="Run npm ci + npm run build on the host first")
    ap.add_argument("--verify-only", action="store_true",
                    help="Only check the committed catalogs; generate nothing. "
                         "Reads .po + languages/*.json and nothing else, so it "
                         "needs no Docker, no Node and no network — this is the "
                         "mode CI runs.")
    args = ap.parse_args()

    locale = args.locale
    po_rel = f"languages/{DOMAIN}-{locale}.po"
    if not (ROOT / po_rel).exists():
        sys.exit(f"ERROR: {po_rel} not found (the committed translation source)")

    print(f"[i18n] Plugin    : {PLUGIN_SLUG}")
    print(f"[i18n] Locale    : {locale}")

    if args.verify_only:
        if args.build_js:
            sys.exit("ERROR: --verify-only and --build-js are contradictory")
        entries = react_entries()
        if not entries:
            sys.exit("ERROR: build/admin/*.js not found — nothing to verify. The "
                     "React bundles are committed, so an empty build/admin/ means "
                     "a broken checkout, not a clean tree.")
        print(f"[i18n] React     : {len(entries)} entries ({', '.join(entries)})")
        print("[i18n] Mode      : VERIFY ONLY — generating nothing")
        # Declare the blind spots rather than letting a green line imply more
        # than it checked (the failure this whole gate exists to prevent).
        print("       Not checked here: whether the .po itself is up to date with")
        print("       the sources (that is make-pot's job), the non-React")
        print("       assets/blocks catalogs, and locales other than "
              f"{locale}.")
        problems, po_date, covered = verify_react_catalogs(
            entries, locale, ROOT / po_rel)
        if problems:
            sys.exit("ERROR: canonical React i18n set is stale or incomplete.\n  "
                     + "\n  ".join(problems)
                     + "\n\nRegenerate with: python bin/build-i18n.py "
                       "(needs Docker), then commit languages/.")
        print(f"[i18n] Verified  : {len(entries)}/{len(entries)} React canonical "
              f"files fresh ({po_date}) and complete ({covered} bundle-msgid pairs)")
        print("[i18n] SUCCESS   : committed catalogs match the committed .po")
        return 0

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

    # The map is regenerated every run rather than committed, so it cannot drift
    # out of step with webpack's entry list the way a checked-in copy would.
    mapping = source_to_bundles(entries)
    if not mapping:
        sys.exit("ERROR: src-react/ produced an empty source->bundle map")
    MAP_FILE.write_text(json.dumps(mapping, indent=1), encoding="utf-8")
    print(f"[i18n] Map       : {MAP_FILE.name} ({len(mapping)} sources)")

    # --use-map + --extensions=jsx are BOTH load-bearing: our .pot references are
    # src-react/**/*.jsx paths, and make-json's default extension set is .js /
    # .min.js only. Drop either flag and the React half silently produces nothing.
    print("[i18n] make-json (plugin-root cwd, --use-map, --extensions=jsx) ...")
    out = run([
        "docker", "exec", args.container, "bash", "-c",
        f"cd {CONTAINER_PLUGIN_DIR} && "
        f"wp i18n make-json {po_rel} languages/ --no-purge --allow-root "
        f"--extensions=jsx --use-map={MAP_FILE.name}",
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

    problems, po_date, covered = verify_react_catalogs(entries, locale, ROOT / po_rel)
    if problems:
        sys.exit("ERROR: canonical React i18n set incomplete:\n  "
                 + "\n  ".join(problems))

    total = len(list(LANGUAGES.glob(f"{DOMAIN}-{locale}-*.json")))
    print(f"[i18n] Verified  : {len(entries)}/{len(entries)} React canonical files "
          f"fresh ({po_date}) and complete ({covered} bundle-msgid pairs); "
          f"{total} total {locale} JSON")
    print("[i18n] SUCCESS   : commit languages/ — clean clone is now reproducible")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

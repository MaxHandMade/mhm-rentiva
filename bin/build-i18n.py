#!/usr/bin/env python3
"""
Regenerate the deterministic, WordPress-resolvable i18n JSON set for MHM Rentiva.

Produces / refreshes: languages/mhm-rentiva-<locale>-<md5>.json — one per
React admin bundle (build/admin/<entry>.js) and one per block editor script
(assets/blocks/<slug>/index.js).

Usage:
    python bin/build-i18n.py [--build-js] [--locale tr_TR] [--container NAME]
    python bin/build-i18n.py --verify-only     # CI mode: no Docker, no Node
    python bin/build-i18n.py --verify-only --plugin-dir ../mhm-rentiva-pro

`--plugin-dir` points every path at another plugin, so the Pro add-on is checked
by THIS implementation rather than a copy of it — the same cross-repo pattern
Pro's composer `react-imports` script already uses. The text domain is read from
the target's own plugin header, never guessed from the directory name: the JSON
filenames WordPress resolves are built from that string.

`--verify-only` runs steps 5 and 6 alone. It reads the .po and languages/*.json and
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
    6. The same three checks per block: for every assets/blocks/<slug>/index.js
       on disk, md5('assets/blocks/<slug>/index.js') names an existing file
       whose `source` is that path, whose revision date equals the .po's, and
       which carries every msgid the .po references from that index.js. An
       index.js with no catalog is a failure, never a skip: BlockRegistry calls
       wp_set_script_translations() for every block, so a missing file is an
       English inspector on a Turkish site. Finally, any <domain>-<locale>-*.json
       whose `source` is neither a React bundle nor a block index.js is reported
       as stray — WordPress never loads it, so it can only mislead a reader.

Block generation (step 3b): a second make-json run with an INLINE identity map
`{"assets/blocks/<slug>/index.js": "assets/blocks/<slug>/index.js", ...}`.
A bare run (no map) would also emit a catalog for every other `.js` reference in
the .po — Pro's .po references src-react/admin/messages/constants.js and
src-react/admin/vehicle-documents/index.js, which would become two stray files
keyed on source paths WordPress never registers. A map drops every reference it
does not name (MakeJsonCommand::reference_map()), so the identity map restricts
the run to exactly the block scripts. Conversely, the React run's --use-map
names only src-react sources, so it never writes block catalogs: the two runs
are disjoint by construction.

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

DEFAULT_CONTAINER = "rentiva-dev-wpcli-1"
# The container runs as www-data, so no --allow-root; WP-CLI needs the raised
# memory limit to parse the Lite catalog.
WP_CLI = "php -d memory_limit=768M /usr/local/bin/wp"
# Jed/gettext context separator (EOT): how a msgctxt entry is keyed in the JSON.
CONTEXT_SEP = "\x04"

# Resolved by configure() before anything else runs. They are module globals
# rather than a threaded-through config object on purpose: this is a
# single-target CLI, and keeping the signatures flat keeps the mutation tests
# that drive these functions directly readable.
PLUGIN_SLUG = ""
DOMAIN = ""
ROOT = Path()
LANGUAGES = Path()
BUILD_ADMIN = Path()
BLOCKS = Path()
CONTAINER_PLUGIN_DIR = ""
MAP_FILE = Path()


def read_text_domain(plugin_dir: Path, slug: str) -> str:
    """The plugin's declared Text Domain, read from its main file's header.

    Derived, not assumed: the JSON filenames WordPress resolves are built from
    this string, so guessing it (e.g. from the directory name) would silently
    produce catalogs WP never loads — exactly the failure this script exists to
    prevent. The directory name is only a fallback, and it is reported.
    """
    main_file = plugin_dir / f"{slug}.php"
    if main_file.exists():
        for line in main_file.read_text(encoding="utf-8", errors="replace").splitlines()[:60]:
            m = re.match(r"^\s*\*?\s*Text Domain:\s*(\S+)", line)
            if m:
                return m.group(1)
    return slug


def configure(plugin_dir: Path) -> None:
    """Point every path at the plugin being processed.

    Pro calls this script across the repo boundary the same way it already calls
    Lite's check-react-imports.php, so the checking logic has exactly one
    implementation. Duplicating it into Pro would recreate the drift class this
    whole gate exists to catch.
    """
    global PLUGIN_SLUG, DOMAIN, ROOT, LANGUAGES, BUILD_ADMIN, BLOCKS, CONTAINER_PLUGIN_DIR, MAP_FILE
    ROOT = plugin_dir.resolve()
    PLUGIN_SLUG = ROOT.name
    DOMAIN = read_text_domain(ROOT, PLUGIN_SLUG)
    LANGUAGES = ROOT / "languages"
    BUILD_ADMIN = ROOT / "build" / "admin"
    BLOCKS = ROOT / "assets" / "blocks"
    CONTAINER_PLUGIN_DIR = f"/var/www/html/wp-content/plugins/{PLUGIN_SLUG}"
    MAP_FILE = ROOT / ".i18n-map.json"


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
    webpack output). These map 1:1 to handle `<slug>-react-<entry>`
    and to WP's md5 basis `build/admin/<entry>.js`."""
    if not BUILD_ADMIN.exists():
        return []
    return sorted(p.stem for p in BUILD_ADMIN.glob("*.js"))


def block_entries() -> list[str]:
    """Block slugs that ship an editor script: assets/blocks/<slug>/index.js.

    Derived from disk, not from the .po: a block whose strings never reached the
    catalog is exactly what the coverage check has to see."""
    if not BLOCKS.exists():
        return []
    return sorted(p.parent.name for p in BLOCKS.glob("*/index.js"))


def block_source(slug: str) -> str:
    """The registered script path WordPress hashes (relative to plugin root)."""
    return f"assets/blocks/{slug}/index.js"


def canonical_block_filename(slug: str, locale: str) -> str:
    """The ONLY filename WordPress will load for a block's editor script."""
    rel = block_source(slug)
    return f"{DOMAIN}-{locale}-{hashlib.md5(rel.encode()).hexdigest()}.json"


def canonical_react_filename(entry: str, locale: str) -> str:
    """The ONLY filename WordPress will load for handle <slug>-react-<entry>."""
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


def po_keys_with_sources(po_path: Path) -> list[tuple[str, list[str]]]:
    """Every live .po entry as (Jed key, [source paths from its `#:` refs]).

    Jed keys are `msgid`, or `msgctxt \\x04 msgid` when a context is present —
    the same shape make-json writes. Shared by the React and block checks so
    both read the catalog the same way.
    """
    text = po_path.read_text(encoding="utf-8")
    out: list[tuple[str, list[str]]] = []

    def unquote(chunk: str) -> str:
        parts = []
        for raw in re.findall(r'^(?:msgid|msgctxt|msgstr)?\s*"(.*)"$', chunk, re.M):
            parts.append(raw.replace('\\"', '"').replace("\\\\", "\\").replace("\\n", "\n"))
        return "".join(parts)

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
        out.append((key, [ref.rsplit(":", 1)[0] for ref in refs]))
    return out


def po_msgids_by_bundle(
    po_path: Path, entries: list[str]
) -> tuple[dict[str, set[str]], set[str]]:
    """Every Jed key the .po contributes to each bundle, derived from `#:` refs.

    Jed keys are `msgid`, or `msgctxt \\x04 msgid` when a context is present —
    the same shape make-json writes, so the two sets are directly comparable.

    Returns (keys_by_bundle, unroutable_prefixes). Only `src-react/admin/<entry>/`
    and `src-react/shared/` can be routed to a bundle; any OTHER `src-react/`
    prefix is returned so the caller can fail on it. Silently ignoring one would
    be fail-open in the worst way: the string would be dropped by the map AND by
    this check, so the catalog would be incomplete and the gate still green —
    the same shape as the bug this whole gate exists to catch.
    """
    by_bundle: dict[str, set[str]] = {e: set() for e in entries}
    unroutable: set[str] = set()

    for key, sources in po_keys_with_sources(po_path):
        for source in sources:
            if source.startswith("src-react/admin/"):
                entry = source.split("/")[2]
                if entry in by_bundle:
                    by_bundle[entry].add(key)
                else:
                    unroutable.add("src-react/admin/%s/" % entry)
            elif source.startswith("src-react/shared/"):
                for entry in by_bundle:
                    by_bundle[entry].add(key)
            elif source.startswith("src-react/"):
                unroutable.add("/".join(source.split("/")[:2]) + "/")

    return by_bundle, unroutable


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
    want_by_bundle, unroutable = po_msgids_by_bundle(po_path, entries)
    po_date = po_revision_date(po_path)
    if not po_date:
        return ([f"{po_path.name} has no PO-Revision-Date to verify against"], "", 0)

    problems: list[str] = []
    for prefix in sorted(unroutable):
        problems.append(
            f"{prefix} is referenced by the .po but maps to no bundle — teach "
            f"source_to_bundles()/po_msgids_by_bundle() about it, or its strings "
            f"ship untranslated while this gate stays green")
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


def po_msgids_by_block(
    po_path: Path, slugs: list[str]
) -> tuple[dict[str, set[str]], set[str]]:
    """Every Jed key the .po references from each block's index.js.

    Returns (keys_by_slug, unroutable_sources). A `.js` reference under
    assets/blocks/ that is not the index.js of a block on disk is unroutable:
    WordPress loads one catalog per registered script, and BlockRegistry
    registers only assets/blocks/<slug>/index.js, so such a string could reach
    no catalog — reported, not ignored (same reasoning as po_msgids_by_bundle).
    block.json references are PHP-side (register_block_type() translates them
    through the .mo), so they are not this check's business.
    """
    by_slug: dict[str, set[str]] = {s: set() for s in slugs}
    unroutable: set[str] = set()
    for key, sources in po_keys_with_sources(po_path):
        for source in sources:
            if not (source.startswith("assets/blocks/") and source.endswith(".js")):
                continue
            parts = source.split("/")
            if len(parts) == 4 and parts[3] == "index.js" and parts[2] in by_slug:
                by_slug[parts[2]].add(key)
            else:
                unroutable.add(source)
    return by_slug, unroutable


def verify_block_catalogs(
    slugs: list[str], locale: str, po_path: Path
) -> tuple[list[str], int]:
    """Check every block editor catalog against the .po it must mirror.

    The React check's three tests, per block: `source`, freshness, completeness.
    An index.js with no catalog FAILS — it is never skipped as "nothing to
    translate": every block in this codebase carries inspector strings, and the
    one way a block legitimately has none is also the way a broken extraction
    looks (its domain argument wrong, so make-pot dropped every call).

    Returns (problems, block_msgid_pairs_checked).
    """
    want_by_slug, unroutable = po_msgids_by_block(po_path, slugs)
    po_date = po_revision_date(po_path)
    if not po_date:
        return ([f"{po_path.name} has no PO-Revision-Date to verify against"], 0)

    problems: list[str] = []
    for source in sorted(unroutable):
        problems.append(
            f"{source} is referenced by the .po but is not the index.js of a "
            f"block on disk — no catalog WordPress loads can carry its strings")
    for slug in slugs:
        fn = canonical_block_filename(slug, locale)
        path = LANGUAGES / fn
        if not path.exists():
            problems.append(f"block {slug} -> {fn} (absent)")
            continue
        data = json.loads(path.read_text(encoding="utf-8"))
        src = data.get("source", "")
        if src != block_source(slug):
            problems.append(f"block {slug} -> {fn} (source={src!r})")
            continue
        json_date = (data.get("translation-revision-date") or "").strip()
        if json_date != po_date:
            problems.append(
                f"block {slug} -> {fn} (STALE: revision-date {json_date!r} != "
                f".po {po_date!r})")
            continue
        have = set(data.get("locale_data", {}).get("messages", {}))
        absent = want_by_slug[slug] - have
        if absent:
            sample = ", ".join(repr(m) for m in sorted(absent)[:5])
            problems.append(
                f"block {slug} -> {fn} ({len(absent)} msgid(s) in the .po but not "
                f"in the catalog: {sample})")

    covered = sum(len(want_by_slug[s]) for s in slugs)
    return (problems, covered)


def find_stray_catalogs(entries: list[str], slugs: list[str], locale: str) -> list[str]:
    """Every <domain>-<locale>-*.json that is neither a React nor a block catalog.

    WordPress resolves exactly one md5 name per registered script, so any other
    file is dead weight that ships — and, worse, reads like coverage to anyone
    counting files. This is the shape a bare make-json run would leave behind.
    """
    canonical = {canonical_react_filename(e, locale) for e in entries}
    canonical |= {canonical_block_filename(s, locale) for s in slugs}
    return sorted(
        jf.name for jf in LANGUAGES.glob(f"{DOMAIN}-{locale}-*.json")
        if jf.name not in canonical
    )


def verify_all(entries: list[str], slugs: list[str], locale: str,
               po_path: Path) -> tuple[list[str], str, int, int]:
    """React (step 5) + blocks (step 6) + stray files, as one verdict."""
    problems, po_date, react_pairs = verify_react_catalogs(entries, locale, po_path)
    block_problems, block_pairs = verify_block_catalogs(slugs, locale, po_path)
    problems += block_problems
    problems += [f"{name} (stray: not the catalog of any React bundle or block "
                 f"script — WordPress never loads it)"
                 for name in find_stray_catalogs(entries, slugs, locale)]
    return (problems, po_date, react_pairs, block_pairs)


def main() -> int:
    ap = argparse.ArgumentParser(description="Regenerate deterministic i18n JSON")
    ap.add_argument("--locale", default="tr_TR")
    ap.add_argument("--plugin-dir", default=None,
                    help="Plugin root to process. Defaults to this script's own "
                         "plugin. The Pro add-on passes its own directory so both "
                         "plugins share one implementation of these checks.")
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

    plugin_dir = (Path(args.plugin_dir) if args.plugin_dir
                  else Path(__file__).resolve().parent.parent)
    if not plugin_dir.is_dir():
        sys.exit(f"ERROR: --plugin-dir {plugin_dir} is not a directory")
    configure(plugin_dir)

    locale = args.locale
    po_rel = f"languages/{DOMAIN}-{locale}.po"
    if not (ROOT / po_rel).exists():
        sys.exit(f"ERROR: {po_rel} not found under {ROOT} "
                 f"(the committed translation source for text domain {DOMAIN!r})")

    print(f"[i18n] Plugin    : {PLUGIN_SLUG}")
    print(f"[i18n] Domain    : {DOMAIN}")
    print(f"[i18n] Locale    : {locale}")

    if args.verify_only:
        if args.build_js:
            sys.exit("ERROR: --verify-only and --build-js are contradictory")
        entries = react_entries()
        if not entries:
            sys.exit("ERROR: build/admin/*.js not found — nothing to verify. The "
                     "React bundles are committed, so an empty build/admin/ means "
                     "a broken checkout, not a clean tree.")
        slugs = block_entries()
        print(f"[i18n] React     : {len(entries)} entries ({', '.join(entries)})")
        print(f"[i18n] Blocks    : {len(slugs)} editor scripts (assets/blocks/*/index.js)")
        print("[i18n] Mode      : VERIFY ONLY — generating nothing")
        # Declare the blind spots rather than letting a green line imply more
        # than it checked (the failure this whole gate exists to prevent).
        print("       Not checked here: whether the .po itself is up to date with")
        print("       the sources (that is make-pot's job), whether a block's")
        print("       block.json strings translate (PHP-side, via the .mo), and")
        print(f"       locales other than {locale}.")
        print("       Also NOT checked: translation QUALITY. Freshness is date")
        print("       equality and completeness is msgid presence, so an edited")
        print("       msgstr that left PO-Revision-Date alone, or a msgid present")
        print("       with an EMPTY msgstr, both pass. Use msgfmt --statistics")
        print("       for that.")
        problems, po_date, covered, block_pairs = verify_all(
            entries, slugs, locale, ROOT / po_rel)
        if problems:
            sys.exit("ERROR: canonical React/block i18n set is stale or incomplete.\n  "
                     + "\n  ".join(problems)
                     + "\n\nRegenerate with: python bin/build-i18n.py "
                       "(needs Docker), then commit languages/.")
        print(f"[i18n] Verified  : {len(entries)}/{len(entries)} React canonical "
              f"files fresh ({po_date}) and complete ({covered} bundle-msgid pairs)")
        print(f"[i18n] Verified  : {len(slugs)}/{len(slugs)} block catalogs fresh "
              f"and complete ({block_pairs} block-msgid pairs); 0 stray")
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
    slugs = block_entries()
    print(f"[i18n] React     : {len(entries)} entries ({', '.join(entries)})")
    print(f"[i18n] Blocks    : {len(slugs)} editor scripts (assets/blocks/*/index.js)")

    # The map is regenerated every run rather than committed, so it cannot drift
    # out of step with webpack's entry list the way a checked-in copy would.
    mapping = source_to_bundles(entries)
    if not mapping:
        sys.exit("ERROR: src-react/ produced an empty source->bundle map")
    # newline="\n": on Windows text mode would write CRLF, and Pro tracks this
    # file — every run would show it modified with no content change.
    MAP_FILE.write_text(json.dumps(mapping, indent=1), encoding="utf-8", newline="\n")
    print(f"[i18n] Map       : {MAP_FILE.name} ({len(mapping)} sources)")

    # --use-map + --extensions=jsx are BOTH load-bearing: our .pot references are
    # src-react/**/*.jsx paths, and make-json's default extension set is .js /
    # .min.js only. Drop either flag and the React half silently produces nothing.
    print("[i18n] make-json (plugin-root cwd, --use-map, --extensions=jsx) ...")
    out = run([
        "docker", "exec", args.container, "bash", "-c",
        f"cd {CONTAINER_PLUGIN_DIR} && "
        f"{WP_CLI} i18n make-json {po_rel} languages/ --no-purge "
        f"--extensions=jsx --use-map={MAP_FILE.name}",
    ])
    print("       " + out.strip().splitlines()[-1] if out.strip() else "")

    # Step 3b — blocks. An inline identity map restricts the run to the block
    # scripts: without a map make-json would also write a catalog for every
    # other `.js` reference in the .po (see the module docblock).
    if slugs:
        block_map = json.dumps({block_source(s): block_source(s) for s in slugs},
                               separators=(",", ":"))
        print("[i18n] make-json (plugin-root cwd, block identity map) ...")
        out = run([
            "docker", "exec", args.container, "bash", "-c",
            f"cd {CONTAINER_PLUGIN_DIR} && "
            f"{WP_CLI} i18n make-json {po_rel} languages/ --no-purge "
            f"'--use-map={block_map}'",
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

    problems, po_date, covered, block_pairs = verify_all(
        entries, slugs, locale, ROOT / po_rel)
    if problems:
        sys.exit("ERROR: canonical React/block i18n set incomplete:\n  "
                 + "\n  ".join(problems))

    total = len(list(LANGUAGES.glob(f"{DOMAIN}-{locale}-*.json")))
    print(f"[i18n] Verified  : {len(entries)}/{len(entries)} React canonical files "
          f"fresh ({po_date}) and complete ({covered} bundle-msgid pairs)")
    print(f"[i18n] Verified  : {len(slugs)}/{len(slugs)} block catalogs fresh and "
          f"complete ({block_pairs} block-msgid pairs); {total} total {locale} JSON")
    print("[i18n] SUCCESS   : commit languages/ — clean clone is now reproducible")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

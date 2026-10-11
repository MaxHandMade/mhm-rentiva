#!/usr/bin/env bash
#
# Regenerate languages/<slug>.pot (default: Lite, languages/mhm-rentiva.pot).
#
# WHY THIS FILE EXISTS AT ALL
# ---------------------------
# The project's own rule is that a .pot regeneration must use the SAME scope the
# committed .pot was built with -- otherwise the diff fills with phantom strings
# that look like real additions and deletions, and a reviewer (or the next agent)
# cannot tell a genuine change from a scope change.
#
# Until now that scope lived nowhere but in somebody's shell history, which made
# the rule unenforceable: there was no way to check what the committed .pot was
# built with except by inferring it from the paths inside the file. This script
# is the recipe, so the scope is a committed fact rather than a memory.
#
# THE SCOPE, AND HOW TO VERIFY IT
# -------------------------------
# --exclude below must stay in sync with what actually ships. The check is not
# "does it look right" but "does regenerating change anything it should not":
#
#   bash bin/make-pot.sh --probe          # writes /tmp/probe.pot, touches nothing
#   diff <(grep '^msgid' languages/mhm-rentiva.pot | sort -u) \
#        <(grep '^msgid' /tmp/probe.pot        | sort -u)
#
# Every difference must be explainable as a real string added or a real string
# deleted. On 2026-08-02 that diff showed 53 removals, and each one traced to
# code deleted earlier in the round (41 SettingsTester, 6 SettingsTestingRenderer,
# 2 VehicleCategory, 4 upload strings) -- the committed .pot was stale, the scope
# was correct. That is the shape of a healthy result; a diff full of strings that
# still exist in src/ means the scope is wrong, not the catalog.
#
# Note make-pot reads the version from the plugin header, so the Project-Id-Version
# line follows the release automatically. languages/ is excluded so the generator
# never reads its own previous output.
#
set -euo pipefail

# PARAMETERS (defaults are Lite's scope -- running with no flags builds exactly
# what this script always built; Pro passes its own plugin dir, slug, exclude):
#
#   bash bin/make-pot.sh [--probe] [--plugin-dir <container path>] [--slug <domain>] [--exclude <list>]
#
#   Pro:  bash ../mhm-rentiva/bin/make-pot.sh \
#           --plugin-dir /var/www/html/wp-content/plugins/mhm-rentiva-pro \
#           --slug mhm-rentiva-pro --exclude bin,tests,vendor,node_modules,build,languages
#
# The probe compares against the catalog of the plugin being probed, e.g. for Pro
# languages/mhm-rentiva-pro.pot (the probe file lands in the container's /tmp).
#
# No --allow-root: the wpcli container runs as www-data, not root.

CONTAINER="${MHM_WPCLI_CONTAINER:-rentiva-dev-wpcli-1}"
PLUGIN_PATH="/var/www/html/wp-content/plugins/mhm-rentiva"
SLUG="mhm-rentiva"
EXCLUDE="bin,tests,vendor,node_modules,build,carveout,languages"
PROBE=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--probe) PROBE=1; shift ;;
		--plugin-dir) PLUGIN_PATH="${2:?--plugin-dir needs a value}"; shift 2 ;;
		--slug) SLUG="${2:?--slug needs a value}"; shift 2 ;;
		--exclude) EXCLUDE="${2:?--exclude needs a value}"; shift 2 ;;
		*) echo "unknown argument: $1" >&2; exit 2 ;;
	esac
done

OUT="languages/$SLUG.pot"
if [[ "$PROBE" == 1 ]]; then
	OUT="/tmp/probe.pot"
	echo "PROBE MODE: writing $OUT (inside the container), leaving the committed catalog alone"
fi

# Git Bash would rewrite the container paths below into Windows paths.
export MSYS_NO_PATHCONV=1
docker exec "$CONTAINER" sh -lc "cd $PLUGIN_PATH && php -d memory_limit=1024M \"\$(command -v wp)\" \
	i18n make-pot . $OUT --slug=$SLUG --exclude=$EXCLUDE"

echo
echo "Scope used: --exclude=$EXCLUDE"
echo "Next: msgmerge the locale catalogs and recompile --"
echo "  msgmerge --update --no-fuzzy-matching --backup=none languages/$SLUG-tr_TR.po languages/$SLUG.pot"
echo "  wp i18n make-mo   languages/$SLUG-tr_TR.po languages/"
echo "  wp i18n make-php  languages/$SLUG-tr_TR.po languages/"
echo "  python bin/build-i18n.py     # React JSON catalogs; needs Docker"
echo
echo "Then translate any new msgid into Turkish before committing: the house rule"
echo "is EN source + TR mandatory, and an empty msgstr passes the freshness gate."

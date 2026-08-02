#!/usr/bin/env bash
#
# Regenerate languages/mhm-rentiva.pot.
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

CONTAINER="${MHM_WPCLI_CONTAINER:-rentiva-dev-wpcli-1}"
PLUGIN_PATH="/var/www/html/wp-content/plugins/mhm-rentiva"
EXCLUDE="bin,tests,vendor,node_modules,build,carveout,languages"

OUT="languages/mhm-rentiva.pot"
if [[ "${1:-}" == "--probe" ]]; then
	OUT="/tmp/probe.pot"
	echo "PROBE MODE: writing $OUT, leaving the committed catalog alone"
fi

docker exec "$CONTAINER" sh -lc "cd $PLUGIN_PATH && php -d memory_limit=1024M \"\$(command -v wp)\" --allow-root \
	i18n make-pot . $OUT --slug=mhm-rentiva --exclude=$EXCLUDE"

echo
echo "Scope used: --exclude=$EXCLUDE"
echo "Next: msgmerge the locale catalogs and recompile --"
echo "  wp i18n update-po languages/mhm-rentiva.pot languages/mhm-rentiva-tr_TR.po"
echo "  wp i18n make-mo   languages/mhm-rentiva-tr_TR.po languages/"
echo "  wp i18n make-php  languages/mhm-rentiva-tr_TR.po languages/"
echo "  python bin/build-i18n.py     # React JSON catalogs; needs Docker"
echo
echo "Then translate any new msgid into Turkish before committing: the house rule"
echo "is EN source + TR mandatory, and an empty msgstr passes the freshness gate."

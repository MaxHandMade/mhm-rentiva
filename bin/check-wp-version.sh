#!/usr/bin/env bash
# Fails unless the WordPress core in <core-dir> is the version a CI leg asked for.
#
# usage: check-wp-version.sh <core-dir> <latest|trunk|X.Y[.Z]>
#
# latest -> the newest release api.wordpress.org reports right now
# trunk  -> a development build (alpha, beta, RC or -src)
# X.Y.Z  -> exactly that version
#
# Without this a leg that downloaded something else (a moved archive, a cached
# tree, an API answer in a new shape) keeps its name and stays green while
# testing a version it does not name.

set -euo pipefail

core_dir=${1:?core dir}
want=${2:?wanted version}

version_file="$core_dir/wp-includes/version.php"
if [[ ! -f "$version_file" ]]; then
	echo "No WordPress core at $core_dir (missing wp-includes/version.php)." >&2
	exit 1
fi

got=$(grep -o "^\$wp_version = '[^']*'" "$version_file" | sed "s/^\$wp_version = '//; s/'\$//")
if [[ -z "$got" ]]; then
	echo "Could not read \$wp_version from $version_file." >&2
	exit 1
fi

case "$want" in
	latest)
		want=$(curl -s https://api.wordpress.org/core/version-check/1.7/ | grep -o '"version":"[^"]*' | head -n 1 | sed 's/"version":"//')
		if [[ -z "$want" ]]; then
			echo "Latest WordPress version could not be found (api.wordpress.org/core/version-check/1.7/)." >&2
			exit 1
		fi
		;;
	trunk)
		if [[ "$got" =~ -(alpha|beta|RC|src) ]]; then
			echo "WordPress $got (trunk) installed."
			exit 0
		fi
		echo "Asked for trunk, but WordPress $got is a release." >&2
		exit 1
		;;
esac

if [[ "$got" != "$want" ]]; then
	echo "Asked for WordPress $want, but $got is installed." >&2
	exit 1
fi

echo "WordPress $got installed, as asked."

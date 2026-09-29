#!/bin/bash
# Install the default applications beside the kernel (Projects' K4, 2026-09-28): the tenant provisioning script's
# last step, and the command for a kernel that was provisioned before the defaults existed. Each application is
# installed by bin/app_install.php from its repository — HR, then Projects — with every standing department
# granted its member role at write and its declared agents hired (the owner's rule for the defaults: hired on
# install, not merely proposed). Run as root on the kernel host.
#
#   sudo bin/install_default_applications.sh <source> [--by <super-admin email>] [--domain <domain>] [--scheme http|https]
#
# <source> is a directory holding the repositories as hr/ and projects/, or a git URL prefix (…/hr.git, …/projects.git
# are appended). Every other option is passed to app_install.php as it is. An application already installed is
# reconciled, not reinstalled.
set -euo pipefail
K=$(cd "$(dirname "$0")/.." && pwd)
SRC=${1:?source directory or git URL prefix}; shift
DEFAULTS=(hr projects)
for key in "${DEFAULTS[@]}"; do
    if [[ "$SRC" =~ ^(https?://|git@|ssh://) ]]; then repo="${SRC%/}/${key}.git"; else repo="${SRC%/}/${key}"; fi
    if [[ ! "$repo" =~ ^(https?://|git@|ssh://) && ! -f "$repo/maludb-os.json" ]]; then
        echo "install_default_applications: no repository for ${key} at ${repo} — skipped" >&2
        continue
    fi
    echo "== ${key} from ${repo}"
    php "$K/bin/app_install.php" apply "$repo" --hire-agents --grant-standing-departments "$@"
done

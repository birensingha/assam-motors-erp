#!/usr/bin/env bash
# AM127 - Mechanic shown as Not Assigned in P55 but present in Main ROT panel.
# RESTRICTED to STAGING and completely READ-ONLY at SQL level.
set -Eeuo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
test -f artisan || { echo AM127_STOP_NOT_STAGING;exit 2; }
URL="https://raw.githubusercontent.com/birensingha/assam-motors-erp/audit/am127-p55-mechanic-binding-20261024/deployment/live-fixes/AM127_P55_MECHANIC_AUDIT/READONLY.php"
curl --connect-timeout 15 --max-time 50 -fsSL "$URL" -o /tmp/AM127_MECHANIC_BINDING_READONLY.php
php -l /tmp/AM127_MECHANIC_BINDING_READONLY.php
php /tmp/AM127_MECHANIC_BINDING_READONLY.php

#!/bin/sh

# IONOS akzeptiert im Cronjob-Feld teilweise nur einen ausfuehrbaren Pfad ohne
# Argumente. Dieser Wrapper haelt den dort eingetragenen Wert deshalb einfach
# und schreibt auch Fehler des PHP-Interpreters in eine dauerhaft erhaltene
# Logdatei ausserhalb des Deploy-Ziels.

set -u

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
verwaltung_dir=$(dirname -- "$script_dir")
project_dir=$(dirname -- "$verwaltung_dir")
log_dir="$project_dir/storage/logs"

mkdir -p "$log_dir"

exec /usr/bin/php8.5 -f "$script_dir/health_check.php" >> "$log_dir/health-cron.log" 2>&1

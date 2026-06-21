#!/usr/bin/env bash
# Run the full Timma -> LatePoint import pipeline in order.
# Token: pass as $1, else the importers fall back to .context/timma/timma_auth.json.
# Extra args (e.g. --dry-run) are forwarded to every step.
#
# ponytail: just the importers in sequence. Does NOT run the one-off financial
# reconciliation (separate_old_owner_revenue / rebuild_feb_payments / fixes) —
# those aren't repeatable. Re-run them by hand if needed.
set -euo pipefail
cd "$(dirname "$0")"

TOKEN_ARG=""
case "${1:-}" in
  --*) : ;;                       # first arg is a flag, no token given
  "")  : ;;
  *)   TOKEN_ARG="--token=$1"; shift ;;
esac
PHP="php -d memory_limit=1024M"

for step in \
  import_timma_agents_services.php \
  import_timma_customers_bookings.php \
  import_timma_packages.php \
  link_package_usage.php \
  import_timma_work_schedules.php
do
  echo "===== $step ====="
  $PHP "$step" $TOKEN_ARG "$@"
done
echo "===== done ====="

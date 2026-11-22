#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-https://dicoma.ddev.site}"
REQUESTS="${REQUESTS:-100}"
CONCURRENCY="${CONCURRENCY:-10}"
MAX_P95_MS="${MAX_P95_MS:-5000}"
ENDPOINTS=( "/" "/login" )
if [[ -n "${DICOMA_LOAD_PATHS:-}" ]]; then
  read -r -a ENDPOINTS <<< "$DICOMA_LOAD_PATHS"
fi
cookie_header=()
if [[ -n "${DICOMA_LOAD_COOKIE:-}" ]]; then
  cookie_header=( -H "Cookie: ${DICOMA_LOAD_COOKIE}" )
fi
work="$(mktemp -d)"
trap 'rm -f -- "$work"/*.csv; rmdir -- "$work"' EXIT

if [[ ! "$REQUESTS" =~ ^[1-9][0-9]*$ || ! "$CONCURRENCY" =~ ^[1-9][0-9]*$ ]]; then
  echo "REQUESTS und CONCURRENCY müssen positive Ganzzahlen sein." >&2
  exit 2
fi

for endpoint in "${ENDPOINTS[@]}"; do
  out="$work/$(echo "$endpoint" | tr '/' '_' | sed 's/^_/root_/').csv"
  seq 1 "$REQUESTS" | xargs -P "$CONCURRENCY" -I '{}' \
    curl -ksS "${cookie_header[@]}" -o /dev/null -w '%{http_code},%{time_total}\n' "${BASE_URL}${endpoint}" > "$out"
  errors="$(awk -F, '$1 < 200 || $1 >= 400 {count++} END {print count+0}' "$out")"
  p95="$(sort -t, -k2,2n "$out" | awk -F, -v n="$REQUESTS" 'NR == int((n*95+99)/100) {printf "%.0f", $2*1000}')"
  echo "$endpoint: requests=$REQUESTS errors=$errors p95=${p95}ms"
  if [[ "$errors" -ne 0 || "$p95" -gt "$MAX_P95_MS" ]]; then
    echo "Lasttestschwelle verletzt: errors=0, p95<=${MAX_P95_MS}ms" >&2
    exit 1
  fi
done

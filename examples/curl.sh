#!/usr/bin/env bash
# GSM EYE Reseller API from the shell. Needs curl and base64.
#
#   export GSMEYE_URL=https://YOUR-DOMAIN
#   export GSMEYE_USER=you@example.com
#   export GSMEYE_KEY=YOUR_API_KEY
#   ./curl.sh accountinfo

set -euo pipefail

: "${GSMEYE_URL:?set GSMEYE_URL}" "${GSMEYE_USER:?set GSMEYE_USER}" "${GSMEYE_KEY:?set GSMEYE_KEY}"

call() { # call <action> [parameters]
  curl -sS -X POST "${GSMEYE_URL%/}/api/index.php" \
    --data-urlencode "username=${GSMEYE_USER}" \
    --data-urlencode "apiaccesskey=${GSMEYE_KEY}" \
    --data-urlencode "requestformat=JSON" \
    --data-urlencode "action=$1" \
    ${2:+--data-urlencode "parameters=$2"}
  echo
}

b64() { printf '%s' "$1" | base64 | tr -d '\n'; }

case "${1:-}" in
  accountinfo)
    call accountinfo ;;

  servicelist) # once per 5 minutes
    call imeiservicelist ;;

  order) # ./curl.sh order <serviceId> <imei> ['{"Field":"value"}']
    custom=""
    [ -n "${4:-}" ] && custom="<CUSTOMFIELD>$(b64 "$4")</CUSTOMFIELD>"
    call placeimeiorder "<PARAMETERS><ID>$2</ID><IMEI>$3</IMEI>${custom}</PARAMETERS>" ;;

  status) # ./curl.sh status <orderId>
    call getimeiorder "<PARAMETERS><ID>$2</ID></PARAMETERS>" ;;

  status-bulk) # ./curl.sh status-bulk '{"a":{"ID":"98214"},"b":{"ID":"98215"}}'
    call getimeiorderbulk "$(b64 "$2")" ;;

  *)
    echo "usage: $0 accountinfo | servicelist | order <serviceId> <imei> [json] | status <orderId> | status-bulk <json>" >&2
    exit 1 ;;
esac

#!/usr/bin/env bash
# Gửi 1 thông báo deploy (JSON) tới Discord Hub trên site, ký HMAC-SHA256 bằng LACA_DEPLOY_KEY.
# Khớp với App\Features\DiscordHub\FileWatch\DeployReceiver::verify():
#   signature = hex(hmac_sha256(key, timestamp + "\n" + raw_body))
#
# Dùng: LACA_DEPLOY_KEY=... SITE_URL=https://example.com laca-deploy-notify.sh body.json
set -euo pipefail

body_file="$1"
: "${LACA_DEPLOY_KEY:?LACA_DEPLOY_KEY chưa được đặt}"
: "${SITE_URL:?SITE_URL chưa được đặt}"

ts="$(date +%s)"
sig="$({ printf '%s\n' "$ts"; cat "$body_file"; } | openssl dgst -sha256 -hmac "$LACA_DEPLOY_KEY" -r | cut -d' ' -f1)"

curl -sS --fail-with-body --max-time 120 --retry 2 --retry-delay 5 \
  -X POST "${SITE_URL%/}/wp-admin/admin-ajax.php?action=laca_discord_hub_deploy" \
  -A "LacaDeploy/1.0 (GitHub Actions)" \
  -H "Content-Type: application/json" \
  -H "X-Laca-Timestamp: ${ts}" \
  -H "X-Laca-Signature: ${sig}" \
  --data-binary @"$body_file"
echo

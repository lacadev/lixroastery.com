#!/usr/bin/env bash
# =============================================================================
# build-release.sh — Build & release lacadev-client theme
#
# Cách dùng:
#   chmod +x build-release.sh
#   ./build-release.sh 3.2 "Thêm widget mới, sửa lỗi mobile"
#
# Script sẽ:
#   1. Cập nhật Version trong style.css
#   2. Cập nhật lacadev-client.json (file server-side)
#   3. Tạo file .zip với tên thư mục đúng (lacadev-client/)
#   4. Hướng dẫn upload lên lacadev.com
# =============================================================================

set -euo pipefail

# ── Cấu hình ──────────────────────────────────────────────────────────────────
THEME_SLUG="lacadev-client"
THEME_DIR="$(cd "$(dirname "$0")" && pwd)"
BUILD_DIR="/tmp/${THEME_SLUG}-build"
OUTPUT_DIR="${THEME_DIR}/releases"

# ── Đọc tham số ───────────────────────────────────────────────────────────────
NEW_VERSION="${1:-}"
CHANGELOG="${2:-Cập nhật theme}"

if [[ -z "$NEW_VERSION" ]]; then
    echo "❌ Thiếu version. Dùng: ./build-release.sh 3.2 \"Mô tả thay đổi\""
    exit 1
fi

TODAY=$(date +%Y-%m-%d)
ZIP_FILENAME="${THEME_SLUG}-${NEW_VERSION}.zip"
ZIP_PATH="${OUTPUT_DIR}/${ZIP_FILENAME}"
JSON_FILE="${THEME_DIR}/theme-server/lacadev-client.json"
STYLE_CSS="${THEME_DIR}/theme/style.css"

echo "🚀 Build lacadev-client v${NEW_VERSION}"
echo "   Changelog: ${CHANGELOG}"
echo ""

# ── Bước 1: Cập nhật Version trong style.css ─────────────────────────────────
echo "📝 Cập nhật style.css → Version: ${NEW_VERSION}"
sed -i.bak "s/^\( \* Version:\).*/\1 ${NEW_VERSION}/" "$STYLE_CSS"
rm -f "${STYLE_CSS}.bak"

# ── Bước 2: Cập nhật info.json ────────────────────────────────────────────────
echo "📝 Cập nhật lacadev-client.json"

DOWNLOAD_URL="https://lacadev.com/theme-updates/${ZIP_FILENAME}"
CHANGELOG_HTML="<h4>${NEW_VERSION} (${TODAY})</h4><ul><li>${CHANGELOG}</li></ul>"

# Đọc changelog cũ và prepend
OLD_CHANGELOG=$(python3 -c "
import json, sys
with open('${JSON_FILE}') as f:
    d = json.load(f)
print(d.get('changelog', ''))
" 2>/dev/null || echo "")

python3 - <<PYEOF
import json

with open('${JSON_FILE}', 'r') as f:
    data = json.load(f)

data['version']       = '${NEW_VERSION}'
data['download_url']  = '${DOWNLOAD_URL}'
data['last_updated']  = '${TODAY}'
data['changelog']     = '''${CHANGELOG_HTML}''' + data.get('changelog', '')

with open('${JSON_FILE}', 'w') as f:
    json.dump(data, f, ensure_ascii=False, indent=2)
PYEOF

echo "   ✅ JSON cập nhật xong"

# ── Bước 3: Tạo zip ───────────────────────────────────────────────────────────
echo "📦 Tạo ${ZIP_FILENAME}..."
mkdir -p "$OUTPUT_DIR"

# Copy vào thư mục tạm để zip có đúng cấu trúc thư mục
rm -rf "$BUILD_DIR"
cp -r "$THEME_DIR" "$BUILD_DIR"

# Xóa các file không cần trong bản phân phối
rm -rf \
    "${BUILD_DIR}/.git" \
    "${BUILD_DIR}/releases" \
    "${BUILD_DIR}/build-release.sh" \
    "${BUILD_DIR}/theme-server" \
    "${BUILD_DIR}/node_modules" \
    "${BUILD_DIR}/.DS_Store"

find "$BUILD_DIR" -name ".DS_Store" -delete
find "$BUILD_DIR" -name "*.map" -delete

# Tạo zip với tên thư mục đúng
(cd /tmp && zip -r "$ZIP_PATH" "${THEME_SLUG}-build/" -x "*.git*" -x "__MACOSX*")
rm -rf "$BUILD_DIR"

ZIP_SIZE=$(du -sh "$ZIP_PATH" | cut -f1)
echo "   ✅ Đã tạo: ${ZIP_PATH} (${ZIP_SIZE})"

# ── Bước 4: Hướng dẫn upload ─────────────────────────────────────────────────
echo ""
echo "════════════════════════════════════════════════════════"
echo "✅ Build xong! Tiếp theo upload lên lacadev.com:"
echo "════════════════════════════════════════════════════════"
echo ""
echo "1. Upload ZIP:"
echo "   scp '${ZIP_PATH}' user@lacadev.com:/path/to/public_html/theme-updates/"
echo ""
echo "2. Upload JSON (cập nhật thông tin version mới):"
echo "   scp '${JSON_FILE}' user@lacadev.com:/path/to/public_html/theme-updates/lacadev-client.json"
echo ""
echo "3. Kiểm tra URL sau khi upload:"
echo "   curl https://lacadev.com/theme-updates/lacadev-client.json"
echo ""
echo "📦 File zip: ${ZIP_PATH}"
echo "📋 File JSON: ${JSON_FILE}"
echo ""
echo "🎉 Các site client sẽ nhận thông báo trong ~12 giờ!"

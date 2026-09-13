#!/usr/bin/env bash
set -euo pipefail

EXTENSION_ID="ses-manager"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${TMPDIR:-/tmp}/ses-manager-release-build"
EXCLUDED_DIR="${BUILD_DIR}/excluded"

EXCLUDE_PATHS=(
  "tests"
  "scripts/debug-custombutton3.php"
  "scripts/debug-identity.php"
  "scripts/debug-smarthost.php"
  "sbin/debug-ses-test.php"
)

restore_excluded() {
  for relative_path in "${EXCLUDE_PATHS[@]}"; do
    if [[ -e "${EXCLUDED_DIR}/${relative_path}" ]]; then
      mkdir -p "${ROOT_DIR}/$(dirname "${relative_path}")"
      mv "${EXCLUDED_DIR}/${relative_path}" "${ROOT_DIR}/${relative_path}"
    fi
  done
}

rm -rf "${BUILD_DIR}"
mkdir -p "${EXCLUDED_DIR}"
trap restore_excluded EXIT

for relative_path in "${EXCLUDE_PATHS[@]}"; do
  if [[ -e "${ROOT_DIR}/${relative_path}" ]]; then
    mkdir -p "${EXCLUDED_DIR}/$(dirname "${relative_path}")"
    mv "${ROOT_DIR}/${relative_path}" "${EXCLUDED_DIR}/${relative_path}"
  fi
done

plesk bin extension -p "${EXTENSION_ID}" -destination "${BUILD_DIR}"

ZIP_PATH="$(find "${BUILD_DIR}" -maxdepth 1 -name "${EXTENSION_ID}-*.zip" -print -quit)"
if [[ -z "${ZIP_PATH}" ]]; then
  echo "Release package was not created." >&2
  exit 1
fi

python3 - "${ZIP_PATH}" "${ROOT_DIR}/meta.xml" <<'PY'
import sys
import zipfile
import tempfile
import os
import re

zip_path = sys.argv[1]
meta_path = sys.argv[2]
blocked_exact = set()
blocked_prefixes = (
    "plib/tests/",
    "plib/var/release-build/",
)
blocked_fragments = (
    "/debug-",
    "._",
)

with open(meta_path, "rb") as meta_file:
    source_meta = meta_file.read()

with tempfile.NamedTemporaryFile(delete=False) as tmp:
    tmp_path = tmp.name

try:
    with zipfile.ZipFile(zip_path) as source, zipfile.ZipFile(tmp_path, "w", zipfile.ZIP_DEFLATED) as target:
        for item in source.infolist():
            if item.filename == "meta.xml":
                info = zipfile.ZipInfo(item.filename, item.date_time)
                info.external_attr = item.external_attr
                target.writestr(info, source_meta)
                continue
            target.writestr(item, source.read(item.filename))
    os.replace(tmp_path, zip_path)
finally:
    if os.path.exists(tmp_path):
        os.unlink(tmp_path)

with zipfile.ZipFile(zip_path) as archive:
    bad = []
    for name in archive.namelist():
        if name in blocked_exact:
            bad.append(name)
            continue
        if any(name.startswith(prefix) for prefix in blocked_prefixes):
            bad.append(name)
            continue
        if any(fragment in name for fragment in blocked_fragments):
            bad.append(name)

if bad:
    print("Release package contains blocked development files:", file=sys.stderr)
    for name in bad[:50]:
        print(" - " + name, file=sys.stderr)
    sys.exit(1)

    meta = archive.read("meta.xml").decode("utf-8")
    version_match = re.search(r"<version>(.*?)</version>", meta)
    release_match = re.search(r"<release>(.*?)</release>", meta)
    if not version_match or not release_match:
        print("Release package meta.xml is missing version or release.", file=sys.stderr)
        sys.exit(1)

print("Release package validation passed: " + zip_path)
PY

echo "Release package created: ${ZIP_PATH}"

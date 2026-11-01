#!/usr/bin/env bash
# Commit (opsional) lalu push ke origin memakai token gh lewat header Authorization, lalu cek sinkron.
# Pakai: infrastructure/gpush.sh "pesan commit"
set -uo pipefail
cd "$(dirname "$0")/.."
if [ $# -gt 0 ]; then
  git add -A
  git commit -qm "$1" || echo "(tidak ada perubahan untuk di-commit)"
fi
BR=$(git rev-parse --abbrev-ref HEAD)
TOKEN=$(gh auth token) || { echo "gh belum login"; exit 1; }
B64=$(printf 'x-access-token:%s' "$TOKEN" | base64 -w0)
G() { git -c credential.helper= -c "http.extraHeader=Authorization: Basic ${B64}" "$@"; }
G push origin "${BR}:${BR}" 2>&1 | tail -4
G fetch origin 2>/dev/null
if [ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/${BR}")" ]; then
  echo "SINKRON dengan GitHub ($(git rev-parse --short HEAD))"
else
  echo "BELUM sinkron dengan GitHub"; exit 1
fi

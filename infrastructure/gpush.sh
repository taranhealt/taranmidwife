#!/usr/bin/env bash
# Commit (opsional) lalu push ke origin memakai token gh (scope workflow), lalu cek sinkron.
# Pakai: infrastructure/gpush.sh "pesan commit"
set -uo pipefail
cd "$(dirname "$0")/.."
if [ $# -gt 0 ]; then
  git add -A
  git commit -qm "$1" || echo "(tidak ada perubahan untuk di-commit)"
fi
BR=$(git rev-parse --abbrev-ref HEAD)
SLUG=$(git remote get-url origin | sed -E 's#.*github\.com[:/]##; s#\.git$##')
TOKEN=$(gh auth token) || { echo "gh belum login"; exit 1; }
git push "https://x-access-token:${TOKEN}@github.com/${SLUG}.git" "${BR}:${BR}" 2>&1 | sed -E 's#//[^@/]*@#//***@#' | tail -4
git fetch origin 2>/dev/null
if [ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/${BR}")" ]; then
  echo "SINKRON dengan GitHub ($(git rev-parse --short HEAD))"
else
  echo "BELUM sinkron dengan GitHub"; exit 1
fi

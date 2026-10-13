#!/usr/bin/env bash
# Runs Python for Claude inside a sandbox: a separate user, no network, its own process table, an empty
# environment, and a private view of the disk where the runner's home folder is hidden except for the site
# checkout. It can read and write the site files but can't reach the Claude subscription token, the GitHub
# token or the internet. Installed by the workflow as /usr/local/bin/python3, python and sitepy.
#
#     python3 .work/edit.py          python3 -c "..."          siteimg site/images/x.jpg '<ops json>'
set -euo pipefail
if [ "${1:-}" = "--tool" ]; then
  tool="${2:-}"
  case "$tool" in /opt/sitetools/*.py) ;; *) echo "Unknown tool"; exit 2 ;; esac
  shift 2
  set -- "$tool" "$@"
fi
ws="${SITE_WORKSPACE:-$PWD}"
case "$PWD/" in "$ws"/*) ;; *) echo "Run from inside the site folder ($ws)."; exit 2 ;; esac
mkdir -p "$ws/.work" 2>/dev/null || true
exec sudo -n env WS="$ws" CWD="$PWD" HIDE="${HOME:-/nonexistent}" \
  unshare --net --pid --fork --mount-proc --mount --propagation private -- sh -c '
    keep=$(mktemp -d)
    mount --bind "$WS" "$keep"
    case "$WS/" in "$HIDE"/*)
      mount -t tmpfs -o mode=755 tmpfs "$HIDE"
      mkdir -p "$WS"
      mount --bind "$keep" "$WS" ;;
    esac
    cd "$CWD"
    exec setpriv --reuid=sitebox --regid=sitebox --clear-groups \
      env -i PATH=/usr/bin:/bin HOME=/tmp LANG=C.UTF-8 PYTHONDONTWRITEBYTECODE=1 MPLBACKEND=Agg \
      /usr/bin/python3 "$@"
  ' sitebox-python "$@"

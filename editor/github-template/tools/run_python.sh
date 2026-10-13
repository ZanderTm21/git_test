#!/usr/bin/env bash
# Runs a Python script that Claude wrote (in .work/) in a sandbox: no network and no secrets in its environment,
# so image code can be written freely without any risk to the Claude subscription token.
#
#     sitepy .work/edit.py [args...]            (installed by the workflow)
#     siteimg site/images/x.jpg '<ops json>'    (runs tools/edit_image.py the same way)
set -euo pipefail
script="${1:-}"
shift || true
if [ "$script" = "--tool" ]; then
  # Fixed helper tools installed by the workflow (read-only for Claude).
  script="${1:-}"; shift || true
  case "$script" in /opt/sitetools/*.py) ;; *) echo "Unknown tool"; exit 2 ;; esac
else
  case "$script" in
    .work/*.py) ;;
    *) echo "Put the script in .work/ (e.g. .work/edit.py) and run: sitepy .work/edit.py"; exit 2 ;;
  esac
fi
[ -f "$script" ] || { echo "$script not found"; exit 2; }
mkdir -p .work
# Keep access to the Python packages installed for this user (Pillow, numpy) after clearing the environment.
pypath="$(python3 -c 'import site, sys; print(":".join([site.getusersitepackages()] + [p for p in sys.path if p]))')"
exec sudo unshare --net -- setpriv --reuid="$(id -u)" --regid="$(id -g)" --clear-groups \
  env -i PATH=/usr/local/bin:/usr/bin:/bin HOME="$PWD/.work" PYTHONPATH="$pypath" PYTHONDONTWRITEBYTECODE=1 LANG=C.UTF-8 \
  python3 "$script" "$@"

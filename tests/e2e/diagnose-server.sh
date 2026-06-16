#!/usr/bin/env bash
# TEMPORARY diagnostic for the e2e webServer SIGSEGV (exit 139), reproducible
# only on the GitHub native-x86_64 runner. Confirmed: php -S segfaults on the
# FIRST request to /user/active while ux-icons parses an icon SVG via
# \DOMDocument (Icon::fromFile -> libxml2). This captures a C backtrace from a
# core dump and empirically tests whether disabling PCRE JIT changes anything.
#
# Remove this script and its workflow step once the cause is fixed.
export DATABASE_URL='sqlite:///%kernel.project_dir%/var/e2e.db'
export APP_ENV=dev

# start php -S with the given extra php flags, hit /user/active once, report
probe () {
  local label="$1"; shift
  rm -f var/e2e.db; rm -rf var/cache/dev
  php -d variables_order=EGPCS "$@" bin/console doctrine:schema:create --quiet
  php -d variables_order=EGPCS "$@" -S 127.0.0.1:8765 -t public >/tmp/srv.out 2>&1 &
  local srv=$!
  sleep 4
  local code
  code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 25 \
           http://127.0.0.1:8765/user/active 2>/dev/null || echo ERR)
  sleep 2
  if kill -0 "$srv" 2>/dev/null; then
    echo ">>> [$label] http=$code — server ALIVE (no crash)"; kill "$srv" 2>/dev/null
  else
    echo ">>> [$label] http=$code — server DIED (SIGSEGV)"
  fi
}

echo "PHP: $(php -v | head -1)"
echo "pcre.jit: $(php -i | grep -i '^pcre.jit')"
echo "libxml:   $(php -i | grep -iE 'libxml.*version|libXML' | head -2 | tr '\n' ' ')"

ulimit -c unlimited
echo '/tmp/core.%e.%p' | sudo tee /proc/sys/kernel/core_pattern >/dev/null 2>&1 || true
echo "core_pattern: $(cat /proc/sys/kernel/core_pattern 2>/dev/null)"
rm -f /tmp/core.*

echo "::group::A) default flags (baseline — expect crash)"
probe "default"
echo "--- request log (info/errors) ---"
grep -vE '\] \[debug\]' /tmp/srv.out | tail -15
echo "::endgroup::"

echo "::group::core backtrace (from run A)"
CORE=$(ls -t /tmp/core.* 2>/dev/null | head -1)
if [ -n "$CORE" ] && command -v gdb >/dev/null; then
  echo "core: $CORE"
  gdb -q -batch -ex 'set pagination off' -ex 'bt' -ex 'echo \n---- BT FULL (top) ----\n' -ex 'bt full' \
      "$(command -v php)" "$CORE" 2>&1 \
    | grep -vE 'No such file|debuginfod|Missing separate' | head -90
else
  echo "no core produced (CORE='$CORE')"
fi
echo "::endgroup::"

echo "::group::B) pcre.jit=0 (does disabling PCRE JIT stop the crash?)"
probe "pcre.jit=0" -d pcre.jit=0
echo "::endgroup::"

exit 0

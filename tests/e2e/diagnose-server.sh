#!/usr/bin/env bash
# TEMPORARY diagnostic for the e2e webServer SIGSEGV (exit 139) seen only on the
# GitHub x86_64 runner. Runs the exact webServer steps (playwright.config.js)
# first plainly — to identify WHICH command crashes — then under gdb to capture
# a C backtrace (which shared library / function faults).
#
# Remove this script and its workflow step once the root cause is found.
#
# Matches playwright.config.js webServer env exactly: DATABASE_URL + APP_ENV=dev,
# APP_DEBUG left to dotenv resolution (the real command sets neither explicitly).
export DATABASE_URL='sqlite:///%kernel.project_dir%/var/e2e.db'
export APP_ENV=dev
PHP=(php -d variables_order=EGPCS)

echo "PHP: $(php -v | head -1)"
echo "ext: $(php -m | tr '\n' ' ')"
echo

# 1) schema:create alone -------------------------------------------------------
echo "::group::PLAIN schema:create"
rm -f var/e2e.db
"${PHP[@]}" bin/console doctrine:schema:create -vvv
echo ">>> schema:create exit code: $?  (139 = SIGSEGV)"
echo "::endgroup::"

# 2) php -S boot + one request -------------------------------------------------
echo "::group::PLAIN php -S + curl /user/active"
"${PHP[@]}" -S 127.0.0.1:8765 -t public >/tmp/srv.out 2>&1 &
SRV=$!
sleep 4
if kill -0 "$SRV" 2>/dev/null; then
  curl -sS -o /dev/null -w 'curl http_code=%{http_code}\n' --max-time 25 \
    http://127.0.0.1:8765/user/active || echo "curl failed rc=$?"
  sleep 1
  kill -0 "$SRV" 2>/dev/null && echo ">>> server ALIVE after request" \
                            || echo ">>> server DIED after first request"
else
  echo ">>> server DIED during boot (before first request)"
fi
kill "$SRV" 2>/dev/null
echo "--- server output (tail) ---"; tail -40 /tmp/srv.out
echo "::endgroup::"

# 3) backtraces under gdb (only meaningful if something above crashed) ---------
if command -v gdb >/dev/null; then
  echo "::group::GDB schema:create backtrace"
  rm -f var/e2e.db
  gdb -q -batch -ex 'set pagination off' -ex run \
      -ex 'echo \n==== BT schema:create ====\n' -ex 'bt' -ex 'info sharedlibrary' \
      --args "${PHP[@]}" bin/console doctrine:schema:create -vvv 2>&1 | tail -60
  echo "::endgroup::"

  echo "::group::GDB php -S backtrace"
  gdb -q -batch -ex 'set pagination off' -ex 'handle SIGPIPE nostop noprint pass' \
      -ex run -ex 'echo \n==== BT php -S ====\n' -ex 'bt' -ex 'info sharedlibrary' \
      --args "${PHP[@]}" -S 127.0.0.1:8765 -t public >/tmp/gdbsrv.out 2>&1 &
  G=$!
  sleep 5
  curl -sS -o /dev/null --max-time 25 http://127.0.0.1:8765/user/active || true
  sleep 2
  kill "$G" 2>/dev/null
  wait "$G" 2>/dev/null
  tail -70 /tmp/gdbsrv.out
  echo "::endgroup::"
else
  echo "gdb not available"
fi

# never fail the job on the diagnostic itself
exit 0

#!/bin/bash
# Deploy this checkout of the ZKTeco PUSH server (beta.terminals, for Lockstep) to production with rsync.
#
#   scripts/deploy.sh [options] <prod-host> <prod-path>
#
# <prod-host> is anything ssh takes: a Host from ~/.ssh/config (which supplies the user, key and port), or user@host.
# <prod-path> is the server's folder there: the one holding Blackfin/ and iclock/ (nginx's root, serving only the
# PUSH endpoints and iclock/file/: changelog.md, 2026-11-15).
#
#   --dry-run   check both ends and show what would change; nothing is changed
#   --delete    also remove files from production that are no longer in this checkout. Never the settings, lib/ (the
#               logs), the clocks' uploads or the firmware in iclock/file/: those are always left alone.
#
# Environment:
#   REMOTE_PHP     production's PHP, for the syntax check and the settings (default php). Use the one PHP-FPM runs.
#   APP_USER       PHP-FPM's user: checks that it can write lib/, where the server logs (through sudo)
#   RELOAD_CMD     run on production at the end, e.g. "sudo systemctl reload php-fpm" (needed when opcache doesn't
#                  check file times: opcache.validate_timestamps=0)
#   TERMINALS_URL  the server's address, e.g. https://terminals.example.com: afterwards, checks that the settings and
#                  the code are not served
#   TEST_SN        with TERMINALS_URL: a clock's serial number. Afterwards asks /iclock/cdata for its options, as the
#                  clock does (the GET in readme.txt).
#
# How: the files go first to a staging folder next to production's (<prod-path>.deploy-staging), where production's PHP
# checks every file's syntax. Only then are they copied into <prod-path>, so a file production's PHP can't parse never
# goes live. Nothing restarts: PHP-FPM reads the new files on the next request (unless opcache needs RELOAD_CMD).
#
# Never copied: Blackfin/Database/config.php (production's database settings and paths), lib/ (logs), the clocks'
# uploads (iclock/ssruser.dat_*, iclock/*_<serial>), iclock/file/ (the firmware: not in git, copied once by hand),
# scripts/, .git and local scratch files. Of config.php, only AM_APP_DIR and PHP_CLI (paths) are read.
#
# Do the steps in changelog.md that this script doesn't: config.php settings, nginx, the firmware.
set -euo pipefail

DRY_RUN=0; DELETE=0; PROD=""; PROD_PATH=""
for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=1 ;;
        --delete) DELETE=1 ;;
        -h|--help) sed -n '2,33p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*) echo "Unknown option $arg (see --help)" >&2; exit 2 ;;
        *) if [ -z "$PROD" ]; then PROD="$arg"; elif [ -z "$PROD_PATH" ]; then PROD_PATH="${arg%/}"; else echo "Too many arguments (see --help)" >&2; exit 2; fi ;;
    esac
done
[ -n "$PROD" ] && [ -n "$PROD_PATH" ] || { echo "Usage: scripts/deploy.sh [--dry-run] [--delete] prod-host prod-path" >&2; exit 2; }
case "$PROD_PATH" in /*) ;; *) echo "prod-path must be absolute: $PROD_PATH" >&2; exit 2 ;; esac
REMOTE_PHP="${REMOTE_PHP:-php}"; APP_USER="${APP_USER:-}"; RELOAD_CMD="${RELOAD_CMD:-}"
TERMINALS_URL="${TERMINALS_URL:-}"; TERMINALS_URL="${TERMINALS_URL%/}"; TEST_SN="${TEST_SN:-}"
STAGING="$PROD_PATH.deploy-staging"
MIN_PHP=70400; MIN_PHP_SHOWN=7.4

HERE="$(cd "$(dirname "$0")/.." && pwd)"
cd "$HERE"
[ -f iclock/cdata.php ] && [ -d Blackfin ] || { echo "Not a checkout of the push server: $HERE" >&2; exit 1; }

# ---- what the person watching sees -----------------------------------------------------------------------------
# Each step is numbered; each action in it says what it does and the command it runs, everything that command prints
# is indented under it ("     | "), and then "ok" or "FAILED". So any message can be traced to the action behind it.
STEP=0; STEP_NAME=""; ACTION=""; DONE=(); STARTED=$SECONDS; WARNINGS=()
step() { STEP=$((STEP + 1)); STEP_NAME="$*"; ACTION=""; printf '\n=== Step %d: %s\n' "$STEP" "$*"; }
# _run "what it does" "the command, as shown" command args...
_run() {
    local what="$1" shown="$2" start=$SECONDS rc=0; shift 2
    ACTION="$what"
    printf '  -> %s\n     $ %s\n' "$what" "$shown"
    "$@" 2>&1 | sed -u 's/^/     | /' || rc=$?
    if [ "$rc" != 0 ]; then
        printf '     FAILED (exit %s) after %ss: %s\n' "$rc" $((SECONDS - start)) "$what" >&2
        return "$rc"
    fi
    printf '     ok (%ss)\n' $((SECONDS - start))
    DONE+=("Step $STEP: $what")
    ACTION=""   # a later stop isn't this action's
}
run() { local what="$1"; shift; _run "$what" "$*" "$@"; }                   # an action here
run_remote() { _run "$1" "[$PROD] $2" remote "$2"; }                         # an action on production
# A yes/no check: prints the question and the answer, and returns the answer (the caller decides what a "no" means)
check() {
    local what="$1"; shift
    ACTION="checking: $what"
    printf '  -> %s ... ' "$what"
    if "$@" >/dev/null 2>&1; then echo yes; ACTION=""; return 0; fi
    echo no; return 1
}
info() { printf '     %s\n' "$*"; }
warn() { printf '     WARNING: %s\n' "$*"; WARNINGS+=("$*"); }
fail() { FAIL_MSG="$*"; exit 1; }
# Any stop, wherever it happens, ends with: the step and action it stopped at, why, whether production's files were
# changed, and what was done before it.
LIVE_COPY=0; FAIL_MSG=""
on_exit() {
    local rc=$?
    [ "$rc" != 0 ] || return 0
    {
        printf '\nSTOPPED at step %d (%s)%s (exit %s).\n' "$STEP" "${STEP_NAME:-starting}" "${ACTION:+, while: $ACTION}" "$rc"
        [ -z "$FAIL_MSG" ] || printf '  %s\n' "$FAIL_MSG"
        if [ "$LIVE_COPY" = 1 ]; then
            echo "Production's files may be partly updated (each file is replaced whole). Fix what failed, then run this again."
        else
            echo "Production's files were not changed."
        fi
        if [ "${#DONE[@]}" = 0 ]; then echo "Nothing was done before that."; else echo "Done before that:"; printf '  - %s\n' "${DONE[@]}"; fi
    } >&2
}
trap on_exit EXIT

q() { printf '%q' "$1"; }
# Runs on production, in the server's folder. The commands are passed as one string, quoted for the remote shell.
remote() { ssh -o BatchMode=yes "$PROD" "cd $(q "$PROD_PATH") && $1"; }
remote_at() { ssh -o BatchMode=yes "$PROD" "$1"; }   # the same, from the ssh user's home folder

# What is never copied, and (with --delete) never removed from production either: rsync leaves excluded paths alone.
EXCLUDES=(
    --exclude=/Blackfin/Database/config.php   # production's database settings and paths
    --exclude=/lib/                           # the server's and PHP's logs (student IDs, PINs)
    --exclude=/iclock/file/                   # the clocks' firmware: not in git, copied to production by hand
    --exclude='/iclock/ssruser.dat_*' --exclude='/iclock/*_[0-9]*'   # what the clocks uploaded (student data)
    --exclude='*.log' --exclude='*.dat_*'
    --exclude=/scripts/ --exclude=/.git --exclude=/.gitignore --exclude=/.claude/
    --exclude=/composer.phar --exclude=/test.php --exclude=/test.txt --exclude=/phpinfo.php --exclude='*~'
    --exclude=/.idea/ --exclude=/.vscode/ --exclude=.DS_Store
)
EXCLUDES_SHOWN="config.php, lib/, iclock/file/, the clocks' uploads, logs, scripts/, .git, ..."

# ---- this machine -----------------------------------------------------------------------------------------------
printf 'Deploying %s to %s:%s%s\n' "$HERE" "$PROD" "$PROD_PATH" "$( [ "$DRY_RUN" = 1 ] && echo ' (dry run: nothing is changed)')"

step "This checkout"
info "Commit:  $(git -C "$HERE" log --oneline -1 2>/dev/null || echo '?')"
BRANCH="$(git -C "$HERE" rev-parse --abbrev-ref HEAD 2>/dev/null || echo '?')"
info "Branch:  $BRANCH"
[ "$BRANCH" = main ] || info "Note: not on main."
if [ -n "$(git -C "$HERE" status --porcelain 2>/dev/null)" ]; then
    info "Note: uncommitted changes here are deployed too:"; git -C "$HERE" status --short | head -20 | sed 's/^/       /'
fi
lint_here() {
    local n=0 bad=0 f out
    while IFS= read -r f; do
        n=$((n + 1))
        out="$(php -l "$f" 2>&1)" || { bad=$((bad + 1)); echo "$out"; }
    done < <(find Blackfin iclock -name '*.php' -not -path 'iclock/file/*' | sort)
    echo "$n files checked, $bad with errors"
    [ "$bad" = 0 ]
}
run "Check every PHP file's syntax with this machine's PHP ($(php -r 'echo PHP_VERSION;'))" lint_here \
    || fail "fix the syntax errors above first"
# Classes are loaded from Composer's classmap (vendor/ is in git): a new class file must be in it
classmap_current() {
    local f missing=0
    while IFS= read -r f; do
        grep -qE "^[[:space:]]*((final|abstract)[[:space:]]+)?(class|interface|trait)[[:space:]]" "$f" || continue
        grep -qF "'/$f'" vendor/composer/autoload_classmap.php || { echo "not in the classmap: $f"; missing=1; }
    done < <(find Blackfin -name '*.php' | sort)
    [ "$missing" = 0 ]
}
run "Check that Composer's classmap lists every class under Blackfin/" classmap_current \
    || fail "run 'composer dump-autoload -o' here (or 'php composer.phar dump-autoload -o'), commit vendor/, then run this again"

# ---- production: is it ready? --------------------------------------------------------------------------------------
step "Checking production"
info "$PROD:$PROD_PATH (nothing is changed there yet)"
check "ssh to $PROD works and $PROD_PATH exists" remote "true" || fail "no folder $PROD_PATH on $PROD, or ssh to $PROD failed"
check "it is the push server (iclock/cdata.php)" remote "test -f iclock/cdata.php" \
    || fail "$PROD_PATH has no iclock/cdata.php: is it the right folder?"
check "production has its settings (Blackfin/Database/config.php)" remote "test -f Blackfin/Database/config.php" \
    || fail "production has no Blackfin/Database/config.php: create it from config.example.php first"
check "production's PHP ($REMOTE_PHP) is $MIN_PHP_SHOWN or later" remote "$REMOTE_PHP -r 'exit(PHP_VERSION_ID >= $MIN_PHP ? 0 : 1);'" \
    || fail "production's $REMOTE_PHP is older than $MIN_PHP_SHOWN, or not there (set REMOTE_PHP to the PHP that PHP-FPM runs)"
info "Production's PHP: $(remote "$REMOTE_PHP -r 'echo PHP_VERSION;'" 2>/dev/null || echo '?')"
check "production has rsync (to copy from the staging folder)" remote "command -v rsync" || fail "install rsync on production"
# AM_APP_DIR (required): the Lockstep app whose `artisan fire_clock_event` runs after each punch; PHP_CLI its PHP
read_setting() { remote "$REMOTE_PHP -r 'error_reporting(0); include \"Blackfin/Database/config.php\"; echo defined(\"$1\") ? $1 : \"$2\";'" 2>/dev/null || true; }
AM_APP_DIR="$(read_setting AM_APP_DIR '')"
PHP_CLI="$(read_setting PHP_CLI /usr/bin/php)"
[ -n "$AM_APP_DIR" ] || fail "production's config.php doesn't set AM_APP_DIR (now required): punches would be stored but the app's tardy and absence rules would never run (changelog.md, 2026-11-15)"
info "AM_APP_DIR (the app): $AM_APP_DIR"
check "the app is there (AM_APP_DIR/artisan)" remote_at "test -f $(q "$AM_APP_DIR/artisan")" \
    || fail "AM_APP_DIR in production's config.php is $AM_APP_DIR, which has no artisan: clock events would not fire"
check "the app's PHP is there (PHP_CLI: $PHP_CLI)" remote_at "test -x $(q "$PHP_CLI")" \
    || fail "PHP_CLI in production's config.php (or the default, /usr/bin/php) is $PHP_CLI, which isn't there: clock events would not fire"
LOG_FILE="$PROD_PATH/lib/zk_push_api_server.log"
check "lib/ is there (the server's log)" remote "test -d lib" || warn "production has no lib/: the server can't write its log (changelog.md, step 3)"
if [ -n "$APP_USER" ]; then
    check "$APP_USER can write lib/ (the server's log)" remote "sudo -u $(q "$APP_USER") test -w lib" \
        || warn "$APP_USER can't write lib/: the server's log and PHP's errors would be lost"
fi
check "the clocks' firmware is there (iclock/file/, not in git)" remote "test -d iclock/file" \
    || warn "production has no iclock/file/: copy the firmware from the old host (changelog.md, step 1)"
info "Production is ready."

DELETE_OPT=(); [ "$DELETE" = 0 ] || DELETE_OPT=(--delete)

if [ "$DRY_RUN" = 1 ]; then
    step "Dry run: what would change on production"
    info "Nothing is copied. Each line is a file that would be sent ('<f' changed or new)$( [ "$DELETE" = 1 ] && echo ", or removed ('*deleting')")."
    _run "List what rsync would change" "rsync --dry-run -rlptz${DELETE_OPT[*]:+ ${DELETE_OPT[*]}} ./ $PROD:$PROD_PATH/ (leaving out $EXCLUDES_SHOWN)" \
        bash -c 'rsync --dry-run -rlptz --itemize-changes "$@" | { grep -v "^\." || true; }' _ "${DELETE_OPT[@]}" "${EXCLUDES[@]}" ./ "$PROD:$PROD_PATH/"
    [ "$DELETE" = 1 ] || info "Files no longer in this checkout stay on production (use --delete to remove them)."
    printf '\nDry run finished in %ss. Nothing was changed.\n' $((SECONDS - STARTED))
    exit 0
fi

# ---- staging, then live ------------------------------------------------------------------------------------------
step "Copying the files to a staging folder on production"
info "$STAGING: next to production's folder, not served. Production's files aren't touched yet."
run_remote "Create the staging folder (kept between deploys, so later copies only send changes)" "mkdir -p $(q "$STAGING")"
_run "Copy this checkout to the staging folder" "rsync -rlptz --delete ./ $PROD:$STAGING/ (leaving out $EXCLUDES_SHOWN)" \
    bash -c 'rsync -rlptz --delete --itemize-changes "$@" | { grep -v "^\." || true; }' _ "${EXCLUDES[@]}" ./ "$PROD:$STAGING/"

step "Checking the files with production's PHP"
LINT_REMOTE="cd $(q "$STAGING") && n=0 && bad=0 && while IFS= read -r f; do n=\$((n+1)); out=\$($REMOTE_PHP -l \"\$f\" 2>&1) || { bad=\$((bad+1)); echo \"\$out\"; }; done < <(find . -name '*.php' | sort) && echo \"\$n files checked, \$bad with errors\" && [ \"\$bad\" = 0 ]"
_run "Check every PHP file's syntax with production's PHP" "[$PROD] $REMOTE_PHP -l on each .php file in $STAGING" remote_at "bash -c $(q "$LINT_REMOTE")" \
    || fail "production's PHP can't parse the files above. Nothing went live; fix them, then run this again."

LINES_BEFORE=""
[ -z "$LOG_FILE" ] || LINES_BEFORE="$(remote_at "wc -l < $(q "$LOG_FILE") 2>/dev/null" || true)"

step "Copying the files into production"
info "From the staging folder into $PROD_PATH. Each line is a file replaced or added$( [ "$DELETE" = 1 ] && echo ", or removed ('*deleting')"); each is replaced whole."
LIVE_RSYNC="rsync -rlpt --itemize-changes ${DELETE_OPT[*]}"
for e in "${EXCLUDES[@]}"; do LIVE_RSYNC+=" $(q "$e")"; done
LIVE_RSYNC+=" $(q "$STAGING/") ./ | { grep -v '^\.' || true; }"
LIVE_COPY=1
_run "Copy from the staging folder into production" "[$PROD] rsync -rlpt${DELETE_OPT[*]:+ ${DELETE_OPT[*]}} $STAGING/ $PROD_PATH/ (the same files left out)" \
    remote "bash -c $(q "set -o pipefail; $LIVE_RSYNC")"
if [ -n "$RELOAD_CMD" ]; then
    run_remote "Reload PHP-FPM (RELOAD_CMD), so opcache drops the old code" "$RELOAD_CMD"
fi
LIVE_COPY=0
[ "$DELETE" = 1 ] || info "Files no longer in this checkout were left on production (--delete removes them)."

# ---- check ------------------------------------------------------------------------------------------------------
step "Checking the server"
if [ -n "$TERMINALS_URL" ]; then
    for path in /Blackfin/Database/config.php /lib/zk_push_api_server.log /vendor/autoload.php /composer.json; do
        code="$(curl -s -o /dev/null -w '%{http_code}' "$TERMINALS_URL$path" || true)"
        case "$code" in
            200) warn "$TERMINALS_URL$path is served (HTTP 200): nginx must serve only the PUSH endpoints and iclock/file/ (changelog.md, 2026-11-15)" ;;
            *) info "GET $path -> HTTP $code (not served: ok)" ;;
        esac
    done
    if [ -n "$TEST_SN" ]; then
        body="$(curl -s -w '\n%{http_code}' "$TERMINALS_URL/iclock/cdata?SN=$TEST_SN&options=all&pushver=2.0.3.779&language=69" || true)"
        code="${body##*$'\n'}"
        info "GET /iclock/cdata?SN=$TEST_SN&options=all -> HTTP $code: $(printf '%s' "$body" | head -1 | cut -c1-80)"
        [ "$code" = 200 ] || warn "the clock's options request didn't answer 200"
    else
        info "Set TEST_SN to a clock's serial number to also ask /iclock/cdata for its options, as the clock does."
    fi
else
    info "Set TERMINALS_URL to check that the settings and the code aren't served."
fi
if [ -n "$LOG_FILE" ]; then
    LINES_NOW="$(remote_at "wc -l < $(q "$LOG_FILE") 2>/dev/null" || true)"
    if [ -n "$LINES_BEFORE" ] && [ -n "$LINES_NOW" ] && [ "$LINES_NOW" -ge "$LINES_BEFORE" ]; then
        NEW_ERRORS="$(remote_at "tail -n $((LINES_NOW - LINES_BEFORE)) $(q "$LOG_FILE") | grep -c 'ERROR' || true")"
        info "The server's log: $((LINES_NOW - LINES_BEFORE)) new lines since the copy, ${NEW_ERRORS:-0} with ERROR."
    fi
    info "Watch the clocks' next uploads: ssh $PROD tail -f $LOG_FILE"
fi

printf '\n=== Deployed in %ss\n' $((SECONDS - STARTED))
printf '  - %s\n' "${DONE[@]}"
if [ "${#WARNINGS[@]}" != 0 ]; then echo "Warnings:"; printf '  - %s\n' "${WARNINGS[@]}"; fi
[ -n "$RELOAD_CMD" ] || echo "If production's opcache doesn't check file times, reload PHP-FPM now (or set RELOAD_CMD)."
echo "Check that a clock's next punch appears in the app."
echo "Done."

#### 2026-11-14: MariaDB or MySQL 8, PHP 7.4 to 8.x, and nothing lost to one error
Tested with the Lockstep app in its testbeds (beta repo, testbed/terminals/: a simulated
clock against 5.6 on PHP 7.4 and MariaDB, and L13 on PHP 8.3 with MariaDB and with MySQL 8.4).

**Deploying to the new host:**
1. Copy the code (not `lib/`, not `Blackfin/Database/config.php`, not the clocks' `iclock/*_<serial>` files), and
   `iclock/file/` from the old host: the clocks' firmware, which is not in git.
2. `Blackfin/Database/config.php` from `config.example.php`: the database, and `AM_APP_DIR` (the Lockstep app's
   folder) and `PHP_CLI` (its PHP, e.g. `/usr/bin/php`). Without them it looks for `/usr/local/vhosts/beta` and
   `/bin/php74`, the old host's.
3. `lib/` writable by the web server: the server's log (`zk_push_api_server.log`) and now PHP's (`php-errors.log`).
   Add both to the app's `AM_ERROR_COLLECTOR_TERMINALS_LOGS`, e.g. `<this folder>/lib/*.log`.
4. Check with a clock (or the GET in readme.txt): its punches appear in the app, and `lib/php-errors.log` stays empty.

**Changes:**
- **Punches are stored on MySQL 8.** The duplicate check compared `updated_at` with the string `'NOW()'`: MariaDB only
  warned (so it never matched), MySQL 8 refuses the query, and every punch was lost. The check now looks for the same
  punch (class, type, time, address, verify mode), which also stops a resent punch being stored twice.
- **Errors no longer end the request.** On PHP 8.1+ mysqli throws on a failed query; it is turned off again, so a
  failed query returns false and is logged, as on PHP 7.4. Each punch in an upload is recorded on its own: one that
  fails is logged (`ERROR: punch not recorded`) and the rest are still stored. A failed insert used to return -1,
  which counted as success: the clock's high-water mark moved past the lost punch and a clock event fired for -1.
- **Nothing but the protocol goes to the clock.** The scripts turned `display_errors` on, so PHP's notices (and on PHP
  8, deprecations and fatal errors) were printed into the responses, some ahead of `GET OPTION FROM` or `OK`. They now
  go to `lib/php-errors.log`; a fatal error also gets one `ERROR:` line in the server's log.
- **A punch from a clock that isn't registered** (or has no school) is kept as an unresolved punch in the clock's
  own time. It was lost: with no school there was no time to store.
- **The school's date is worked out in PHP**, not with `CONVERT_TZ`, which needs the database's time zone tables
  (without them no class was ever found).
- **The hand-off** (`artisan fire_clock_event`) takes the app and PHP from config.php, checks that artisan is there
  and its exit status, and no longer changes the request's working directory.
- PHP 8 messages fixed: optional parameters before required ones, a dynamic property, `addslashes(null)`, USERINFO's
  log line (an undefined variable and an array as a string), the unknown clock's `$this->terminal['id']`, and guards
  for a missing terminal, school timezone or short input line. `split()` (gone since PHP 7) replaced.
- `updateRecord` escaped values twice (O'Brien was stored as O\'Brien) and turned null into ''.
- A GetFile result's file name is reduced to its base name (it comes from the request).

**If something stops working:**
- *No punches arrive in the app:* look in `lib/zk_push_api_server.log` for `MySQL ERROR` or `ERROR:` lines, and in
  `lib/php-errors.log`. "cannot connect to the database" is config.php.
- *Punches are stored but no tardies or absences follow:* look for `Error encountered firing clock event`: "no
  artisan in" means `AM_APP_DIR` is wrong; an exit status means the app's command failed (its `storage/logs`).
- *A resent punch now isn't stored again:* that is the duplicate check working; it was always meant to.
- To go back: the first commit (e15d062) is the code as it ran before.

#### 9/12/2017
  - When matching time clock events to student/classes, improved matching by ensuring student_class and school_class are not deleted and active, and actually in session

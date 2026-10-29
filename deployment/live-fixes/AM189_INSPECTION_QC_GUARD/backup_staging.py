#!/usr/bin/env python3
"""AM189 STAGING database backup only: no Laravel/DB writes.
Usage: python3 backup_staging.py ~/domains/assammotors.com/assam-erp-staging
The backup contains confidential customer information: do not publish or upload it.
"""
from pathlib import Path
from datetime import datetime, timezone
import gzip
import hashlib
import json
import os
import shutil
import subprocess
import sys
import tempfile

EXPECTED_DB = "u956497103_assam_staging"

def stop(message):
    raise SystemExit("AM189_BACKUP_STOP " + message)

if len(sys.argv) != 2:
    stop("Usage: backup_staging.py STAGING_LARAVEL_ROOT")
root = Path(sys.argv[1]).expanduser().resolve()
if "staging" not in str(root).lower() or not (root / "artisan").is_file():
    stop("NOT_A_STAGING_LARAVEL_ROOT")
if not (root / "vendor/autoload.php").is_file():
    stop("LARAVEL_VENDOR_MISSING")

# Read Laravel's effective database configuration. The password is captured in
# Python memory, never printed or placed in the command-line arguments.
php = r"""
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$n = config('database.default');
$c = config('database.connections.'.$n);
echo json_encode([
    'driver' => $c['driver'] ?? null,
    'database' => $c['database'] ?? null,
    'host' => $c['host'] ?? null,
    'port' => $c['port'] ?? 3306,
    'username' => $c['username'] ?? null,
    'password' => $c['password'] ?? null,
    'unix_socket' => $c['unix_socket'] ?? null,
], JSON_THROW_ON_ERROR);
"""
result = subprocess.run(["php", "-r", php], cwd=root, text=True, capture_output=True)
if result.returncode:
    stop("LARAVEL_CONFIG_READ_FAILED")
try:
    c = json.loads(result.stdout)
except (ValueError, TypeError):
    stop("INVALID_LARAVEL_DB_CONFIG")
if c.get("driver") != "mysql" or c.get("database") != EXPECTED_DB:
    stop("DATABASE_MISMATCH_EXPECTED_STAGING")
if not isinstance(c.get("username"), str) or not c["username"]:
    stop("DATABASE_USERNAME_MISSING")
if not isinstance(c.get("host"), str) or not c["host"]:
    stop("DATABASE_HOST_MISSING")
tool = shutil.which("mariadb-dump") or shutil.which("mysqldump")
if not tool:
    stop("MARIADB_DUMP_TOOL_NOT_FOUND")

folder = Path.home() / "am-secure-db-backups"
folder.mkdir(mode=0o700, exist_ok=True)
os.chmod(folder, 0o700)
if shutil.disk_usage(folder).free < 100 * 1024 * 1024:
    stop("INSUFFICIENT_DISK_FREE_SPACE")
stamp = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S_UTC")
destination = folder / ("assam_staging_" + stamp + ".sql.gz")
if destination.exists():
    stop("BACKUP_FILENAME_ALREADY_EXISTS")
env = os.environ.copy()
env["MYSQL_PWD"] = str(c.get("password") or "")
cmd = [
    tool,
    "--single-transaction",
    "--quick",
    "--skip-lock-tables",
    "--default-character-set=utf8mb4",
    "--no-tablespaces",
    "--hex-blob",
    "--triggers",
    "--routines",
    "--events",
    "--host=" + c["host"],
    "--port=" + str(c.get("port") or "3306"),
    "--user=" + c["username"],
    "--databases",
    EXPECTED_DB,
]
if c.get("unix_socket"):
    cmd.insert(1, "--socket=" + str(c["unix_socket"]))

print("AM189_BACKUP_DATABASE", EXPECTED_DB, flush=True)
print("AM189_BACKUP_DESTINATION", str(destination), flush=True)
try:
    with tempfile.TemporaryFile(mode="w+b") as errors:
        with gzip.open(destination, "xb", compresslevel=6) as compressed:
            proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=errors, env=env)
            assert proc.stdout is not None
            shutil.copyfileobj(proc.stdout, compressed, length=1024 * 1024)
            proc.stdout.close()
            code = proc.wait()
        if code:
            errors.seek(0)
            # The dump's stderr can contain infrastructure details. Do not
            # print credentials or dump contents into the ChatGPT conversation.
            stop("DUMP_COMMAND_FAILED_EXIT_" + str(code) +
                 "_SEE_HOSTINGER_DATABASE_USER_PRIVILEGES")
    if destination.stat().st_size < 100:
        stop("DUMP_TOO_SMALL")
    uncompressed = 0
    with gzip.open(destination, "rb") as source:
        while True:
            block = source.read(1024 * 1024)
            if not block:
                break
            uncompressed += len(block)
    if uncompressed < 1024:
        stop("DUMP_CONTENT_TOO_SMALL")
    hash_value = hashlib.sha256()
    with destination.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            hash_value.update(chunk)
    os.chmod(destination, 0o600)
    print("AM189_BACKUP_VERIFIED=YES")
    print("AM189_BACKUP_COMPRESSED_BYTES", destination.stat().st_size)
    print("AM189_BACKUP_UNCOMPRESSED_BYTES", uncompressed)
    print("AM189_BACKUP_SHA256", hash_value.hexdigest())
    print("AM189_NO_DATABASE_CHANGES")
    print("AM189_MIGRATION_NOT_RUN")
except BaseException:
    destination.unlink(missing_ok=True)
    raise

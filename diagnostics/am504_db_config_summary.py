#!/usr/bin/env python3
"""AM504: safe, read-only snapshot of Laravel's database configuration.
Only pre-approved classification labels are emitted. No raw hosts, database
names, usernames, passwords, secrets or connection URLs are printed.
"""
from pathlib import Path
import re

root = Path.cwd()
env_file = root / ".env"
config_file = root / "config/database.php"
cache_file = root / "bootstrap/cache/config.php"

wanted = {"DB_HOST", "DB_SOCKET", "DB_UNIX_SOCKET", "DB_URL", "DB_CONNECTION",
          "SESSION_DRIVER", "CACHE_STORE", "CACHE_DRIVER", "QUEUE_CONNECTION"}
values = {}
if env_file.is_file():
    for line in env_file.open(encoding="utf-8", errors="replace"):
        matched = re.match(r"^\s*([A-Z_]+)\s*=\s*(.*)$", line)
        if matched and matched.group(1) in wanted:
            value = matched.group(2).strip()
            if len(value) >= 2 and value[0] == value[-1] and value[0] in ("'", '"'):
                value = value[1:-1]
            values[matched.group(1)] = value

host = values.get("DB_HOST", "")
if not host:
    host_kind = "unset"
elif host.lower() == "localhost":
    host_kind = "localhost_unix_socket_possible"
elif host == "127.0.0.1":
    host_kind = "ipv4_loopback_tcp"
elif host == "::1":
    host_kind = "ipv6_loopback"
elif re.fullmatch(r"\d{1,3}(?:\.\d{1,3}){3}", host):
    host_kind = "other_ipv4"
else:
    host_kind = "hostname_or_other"

print("AM504_LARAVEL_CONFIG_READONLY")
print("ENV_PRESENT:", env_file.is_file())
print("CONFIG_CACHED:", cache_file.is_file())
print("DB_CONNECTION_KIND:", values.get("DB_CONNECTION", "unset")
      if values.get("DB_CONNECTION") in ("mysql", "mariadb", "sqlite", "pgsql")
      else "other_or_unset")
print("DB_HOST_KIND:", host_kind)
print("DB_URL_PRESENT:", bool(values.get("DB_URL")))
print("DB_SOCKET_SET:", bool(values.get("DB_SOCKET") or values.get("DB_UNIX_SOCKET")))
for key in ("SESSION_DRIVER", "QUEUE_CONNECTION", "CACHE_STORE", "CACHE_DRIVER"):
    value = values.get(key, "")
    allowed = {"database", "file", "redis", "array", "sync", "null", "memcached", "dynamodb", "cookie"}
    print(key + "_KIND:", value if value in allowed else "other_or_unset")
if config_file.is_file():
    s = config_file.read_text(encoding="utf-8", errors="replace")
    print("DATABASE_PHP_PERSISTENT_OPTION_PRESENT:", bool(re.search(r"PDO::ATTR_PERSISTENT", s)))
else:
    print("DATABASE_PHP_PRESENT: False")
print("DONE: no credentials or connection names shown; no DB connections opened")

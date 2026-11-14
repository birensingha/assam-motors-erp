#!/usr/bin/env python3
"""AM504: offline, read-only timeline of MySQL 2002 connection errors in Laravel logs.
Outputs only timestamps, reason categories and counts. Never outputs SQL, messages,
customer/staff information, host names, usernames or secrets.
Run from deployed Laravel application root.
"""
from collections import Counter, defaultdict
from datetime import datetime
from pathlib import Path
import re

p = Path("storage/logs/laravel.log")
if not p.is_file():
    print("AM504: Laravel log not found.")
    raise SystemExit(2)

header = re.compile(r"^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]")
code = "SQLSTATE[HY000] [2002]"
categories = (
    ("operation_not_permitted", "Operation not permitted"),
    ("socket_file_missing", "No such file or directory"),
    ("connection_refused", "Connection refused"),
    ("connection_timed_out", "Connection timed out"),
    ("connection_limit", "Too many connections"),
)
minute_counts = Counter()
category_counts = Counter()
last_occurrence = {}
distinct = set()
last_timestamp = None
with p.open(encoding="utf-8", errors="replace") as stream:
    for line in stream:
        match = header.match(line)
        if match:
            last_timestamp = match.group(1)
        if code not in line:
            continue
        category = next((name for name, label in categories if label.lower() in line.lower()), "other_2002")
        timestamp = last_timestamp or "unknown"
        # Every exception may appear twice (exception + stack trace); count at most
        # once per second and category to avoid misleading "incident" counts.
        second_category = (timestamp, category)
        if second_category in distinct:
            continue
        distinct.add(second_category)
        minute = timestamp[:16] if timestamp != "unknown" else "unknown"
        minute_counts[minute] += 1
        category_counts[category] += 1
        last_occurrence[category] = timestamp

print("AM504_MYSQL_2002_TIMELINE")
print("LOG_LAST_MODIFIED_LOCAL_SERVER_TIME:", datetime.fromtimestamp(p.stat().st_mtime).isoformat(timespec="seconds"))
print("UNIQUE_TIMESTAMP_CATEGORY_SIGNATURES:", len(distinct))
print("CATEGORIES")
for label, count in category_counts.most_common():
    print(label, count, "last=", last_occurrence[label])
print("TOP_ERROR_MINUTES")
for minute, count in minute_counts.most_common(12):
    print(minute, count)
print("LAST_ERROR_MINUTES")
for minute, count in sorted(minute_counts.items())[-12:]:
    print(minute, count)
print("NOTE: timezone for Laravel log timestamps is not inferred; per-second de-duplication may undercount.")
print("DONE (read-only)")

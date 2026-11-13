#!/usr/bin/env python3
"""AM504: privacy-safe classification of MySQL SQLSTATE HY000 / driver code 2002.
Read-only. No query text, secrets, or stack trace are printed.
"""
from collections import Counter, deque
from pathlib import Path
import re

log = Path("storage/logs/laravel.log")
if not log.is_file():
    print("RESULT: Laravel log not found in current directory")
    raise SystemExit(2)

# Scan the tail only. A 14MB Laravel log is small, but avoid dumping its contents.
with log.open(errors="replace") as stream:
    lines = deque(stream, maxlen=4000)

reasons = (
    ("operation_not_permitted", r"operation not permitted"),
    ("connection_refused", r"connection refused"),
    ("socket_not_found", r"no such file or directory|failed to open stream"),
    ("connection_timed_out", r"timed out|timeout"),
    ("dns_lookup_failed", r"php_network_getaddresses|name or service not known|temporary failure in name resolution|unknown mysql server host"),
    ("too_many_connections", r"too many connections|too many user connections|max_user_connections"),
    ("permission_denied", r"permission denied|access denied"),
    ("cannot_assign_address", r"cannot assign requested address"),
)

entries = {}
last_timestamp = "unknown"
for line in lines:
    match = re.search(r"\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]", line)
    if match:
        last_timestamp = match.group(1)
    if "SQLSTATE[HY000] [2002]" not in line:
        continue
    lower = line.lower()
    reason = next((label for label, pattern in reasons if re.search(pattern, lower)), "other_2002")
    # Same exception is often printed twice by Laravel; show one row per timestamp and reason.
    entries[(last_timestamp,reason)] = entries.get((last_timestamp,reason),0) + 1

print("AM504_SQL_2002_CLASSIFIER_V1")
print("CATEGORY_COUNTS")
for label, number in sorted(Counter(label for _,label in entries).items()):
    print(label, number)
print("RECENT_SIGNATURES")
for (timestamp,label), n in list(entries.items())[-15:]:
    print(timestamp, label, "match_lines="+str(n))
print("DONE -- diagnostic read-only; query text and credentials not printed")

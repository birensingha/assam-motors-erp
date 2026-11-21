#!/usr/bin/env python3
"""AM245: read-only, secret-safe inventory for integrating Customer Feedback.
Run from deployed Laravel root. Prints structural metadata ONLY, no source code,
secrets, .env values, customer records, tokens, SQL or private log content.
"""
from pathlib import Path
import json
import sys

root = Path.cwd().resolve()

def safe_file(path):
    """Ignore symlinks and anything resolving outside the Laravel root."""
    try:
        return not path.is_symlink() and path.resolve().is_relative_to(root) and path.is_file()
    except (OSError, RuntimeError):
        return False

def safe_dir(path):
    """Never inventory through a symlinked directory, including ancestors."""
    try:
        relative = path.relative_to(root)
        current = root
        for part in relative.parts:
            current = current / part
            if current.is_symlink():
                return False
        return path.is_dir() and path.resolve().is_relative_to(root)
    except (OSError, RuntimeError, ValueError):
        return False

if not safe_file(root / "artisan"):
    print("ERROR: Run in the Laravel directory containing artisan")
    sys.exit(2)

print("=== AM245 STAGING INTEGRATION PREFLIGHT ===")
print("ARTISAN_PRESENT: yes")
print("LARAVEL_COMPOSER_PRESENT:", safe_file(root / "composer.json"))
print("APP_MODELS_PRESENT:", safe_dir(root / "app/Models"))
print("ROUTES_WEB_PRESENT:", safe_file(root / "routes/web.php"))
print("ROUTES_API_PRESENT:", safe_file(root / "routes/api.php"))

if safe_file(root / "composer.json"):
    try:
        spec = json.loads((root / "composer.json").read_text(errors="replace"))
        print("LARAVEL_VERSION_CONSTRAINT:",
              spec.get("require", {}).get("laravel/framework", "unspecified")[:30])
    except (OSError, ValueError):
        print("LARAVEL_VERSION_CONSTRAINT: unreadable")

def relevant_files(folder, suffix, terms, cap=35):
    if not safe_dir(folder):
        return []
    matches = []
    for path in folder.rglob("*"):
        if not safe_file(path) or not path.name.endswith(suffix):
            continue
        rel = str(path.relative_to(root))
        low = rel.lower()
        if any(term in low for term in terms):
            matches.append(rel)
    return sorted(matches)[:cap]

terms = ("customer", "vehicle", "jobcard", "job_card",
         "feedback", "complaint", "review", "ledger", "whatsapp")
admin_terms = ("customer", "vehicle", "jobcard", "job_card",
               "admin", "feedback", "complaint", "review")
view_terms = ("sidebar", "nav", "menu", "layout", "admin", "customer",
              "feedback", "whatsapp", "master")

for title, folder, suffix, keywords, cap in [
    ("RELATED_MODELS", root / "app/Models", ".php", terms, 35),
    ("RELATED_CONTROLLERS", root / "app/Http/Controllers", ".php", admin_terms, 40),
    ("RELATED_BLADE_VIEWS", root / "resources/views", ".blade.php", view_terms, 40)
]:
    values = relevant_files(folder, suffix, keywords, cap)
    print(title + ":")
    if not values:
        print("  none_found")
    for value in values:
        print(" ", value)

route_dir = root / "routes"
print("ROUTE_FILES:")
if safe_dir(route_dir):
    route_files = sorted(p for p in route_dir.glob("*.php") if safe_file(p))[:25]
    for path in route_files:
        print(" ", str(path.relative_to(root)))
    print("ROUTE_MARKERS (file:line:topic, no source excerpts):")
    route_topics = ("customer", "vehicle", "job_card", "jobcard",
                    "feedback", "admin", "sidebar")
    shown = 0
    for path in route_files:
        if shown >= 80:
            break
        try:
            with path.open(errors="replace") as src:
                for number, line in enumerate(src, 1):
                    found = [word for word in route_topics if word in line.lower()]
                    if found and ("Route::" in line or "->name(" in line):
                        print(" ", str(path.relative_to(root))
                              + ":" + str(number) + ":" + ",".join(found[:3]))
                        shown += 1
                        if shown >= 80:
                            break
        except OSError:
            pass
    if shown >= 80:
        print("  additional route markers omitted (80-line privacy/output limit)")

for path in (
    "app/Providers/AppServiceProvider.php",
    "app/Providers/AuthServiceProvider.php",
    "bootstrap/app.php",
    "resources/views/layouts/app.blade.php"
):
    print("CANDIDATE:", path, "present" if safe_file(root / path) else "absent")
print("=== END (READ-ONLY; NO SECRETS OR CUSTOMER DATA) ===")

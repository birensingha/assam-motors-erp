#!/usr/bin/env python3
"""AM476: STAGING-only authenticated, GET-only live-contract probe.

Environment:
  AM_STAGING_ADMIN_TOKEN   ERP Admin bearer (required)
  AM_STAGING_STAFF_TOKEN   genuine non-admin Staff bearer (optional)
No token values, staff names or financial balances are printed.
Does not deploy, mutate ERP rows or accept arbitrary production endpoints.
"""
from __future__ import annotations

import argparse
import json
import os
import ssl
import sys
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlsplit
from urllib.request import Request, build_opener, HTTPSHandler, HTTPRedirectHandler

ALLOWED_ORIGIN = "https://staging.assammotors.com"
PREFIX = "/api/v1"
MAX_JSON = 256 * 1024


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def get_json(path: str, token: str | None):
    if not path.startswith("/admin/mobile/finance/v1/") or "//" in path or ".." in path:
        raise ValueError("Only allowlisted finance-v1 GET checks are permitted")
    target = ALLOWED_ORIGIN + PREFIX + path
    parts = urlsplit(target)
    if parts.scheme != "https" or parts.netloc != "staging.assammotors.com":
        raise ValueError("Production or external host refused")
    opener = build_opener(HTTPSHandler(context=ssl.create_default_context()), NoRedirect())
    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = "Bearer " + token
    request = Request(target, headers=headers, method="GET")
    try:
        with opener.open(request, timeout=15) as response:
            code = response.status
            raw = response.read(MAX_JSON + 1)
            content_type = response.headers.get("content-type", "")
    except HTTPError as exc:
        code = exc.code
        raw = exc.read(MAX_JSON + 1)
        content_type = exc.headers.get("content-type", "")
    if len(raw) > MAX_JSON:
        raise ValueError("Oversized ERP response refused")
    if "json" not in content_type.lower():
        return code, None
    try:
        data = json.loads(raw)
    except (ValueError, UnicodeDecodeError):
        return code, None
    return code, data if isinstance(data, dict) else None


def must(condition: bool, description: str) -> None:
    if not condition:
        raise RuntimeError(description)
    print("PASS", description)


def verify(admin: str, staff: str | None, staff_id: str | None) -> None:
    path = "/admin/mobile/finance/v1/payees?type=STAFF"
    code, body = get_json(path, None)
    must(code in {401, 403} and (body is None or body.get("contract") != "am-finance-v1"),
         "Anonymous staff-payee GET denied")
    code, body = get_json(path, admin)
    must(code == 200 and isinstance(body, dict) and
         body.get("contract") == "am-finance-v1" and
         isinstance(body.get("payees"), list),
         "Authenticated Admin gets versioned Staff payees array")
    payees = body["payees"]
    must(len(payees) <= 100 and len({str(x.get("id")) for x in payees}) == len(payees)
         and all(isinstance(x, dict) and str(x.get("id", "")).isdigit()
                 and x.get("type") == "STAFF" and str(x.get("name", "")).strip()
                 for x in payees),
         "Staff payees are bounded, uniquely identified and type-scoped")

    code, body = get_json("/admin/mobile/finance/v1/payees?type=VENDOR", admin)
    must(code == 409 and isinstance(body, dict) and body.get("success") is False,
         "Unsupported Vendor adapter fails closed without fabricated outstanding")

    for typ in ("PART", "LABOUR", "OSL"):
        path = "/admin/mobile/finance/v1/master-items?" + urlencode(
            {"type": typ, "q": "", "limit": 1})
        code, data = get_json(path, admin)
        must(code == 200 and isinstance(data, dict)
             and data.get("contract") == "am-finance-v1"
             and data.get("resource") == "master-items"
             and isinstance(data.get("items"), list)
             and len(data["items"]) <= 1
             and all(x.get("type") == typ for x in data["items"]),
             f"Authenticated Master search {typ} contract verified")

    if staff_id is not None:
        if not staff_id.isdecimal() or int(staff_id) <= 0:
            raise ValueError("Staff ID must be a positive numeric ERP ID")
        path = "/admin/mobile/finance/v1/open-references?" + urlencode(
            {"payee_type": "STAFF", "payee_id": staff_id})
        code, body = get_json(path, admin)
        must(code == 200 and isinstance(body, dict)
             and body.get("contract") == "am-finance-v1"
             and isinstance(body.get("references"), list),
             "Authenticated Staff outstanding references contract available")
        references = body["references"]
        must(len(references) <= 100
             and len({str(x.get("id")) for x in references}) == len(references)
             and all(str(x.get("payee_id")) == staff_id
                     and x.get("payee_type") == "STAFF"
                     and isinstance(x.get("outstanding_amount"), str)
                     and x["outstanding_amount"].endswith(
                         tuple(f".{i:02d}" for i in range(100)))
                     for x in references),
             "Outstanding references are bounded, unique, exact-string and Staff-scoped")
    else:
        print("SKIP outstanding readback: --staff-id not supplied")

    if staff:
        code, _ = get_json("/admin/mobile/finance/v1/payees?type=STAFF", staff)
        must(code == 403, "Real non-admin Staff bearer denied Admin finance")
    else:
        print("SKIP real Staff denial: AM_STAGING_STAFF_TOKEN not supplied")


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Read-only Assam Motors STAGING Finance API acceptance probe")
    parser.add_argument("--staff-id", default=None,
                        help="Numeric Staff master ID for optional outstanding read")
    args = parser.parse_args()
    token = os.environ.get("AM_STAGING_ADMIN_TOKEN", "").strip()
    if not token or any(c.isspace() for c in token):
        parser.error("Set AM_STAGING_ADMIN_TOKEN securely before running")
    staff = os.environ.get("AM_STAGING_STAFF_TOKEN", "").strip() or None
    if staff and any(c.isspace() for c in staff):
        parser.error("AM_STAGING_STAFF_TOKEN contains whitespace")
    try:
        verify(token, staff, args.staff_id)
    except (RuntimeError, ValueError, URLError) as error:
        print("FAIL", type(error).__name__, str(error), file=sys.stderr)
        return 1
    print("STAGING READ-ONLY FINANCE CONTRACT PROBE PASS")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

#!/usr/bin/env python3
"""AM207 actual STAGING assignment UI/controller anchors, read-only.
No customer/vehicle data values, DB writes, caches, configs or source edits.
"""
from pathlib import Path
import re,sys
if len(sys.argv)!=2:raise SystemExit("USAGE audit.py STAGING_LARAVEL_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM207_STOP_NOT_STAGING")

def read(rel):
    file=root/rel
    if not file.is_file():return []
    return file.read_bytes().decode("utf-8","surrogateescape").splitlines()

def excerpt(label,lines,start,limit=90):
    print("=== "+label+" ===")
    if not lines:
        print("MISSING");return
    end=min(len(lines),start+limit)
    for i in range(max(0,start),end):
        line=lines[i]
        if re.search(r"\.env|password|bearer|api.?token|secret|authorization",line,re.I):
            line="[REDACTED]"
        print(str(i+1)+": "+line[:220])
    print("END_EXCERPT")

print("=== AM207 ASSIGNMENT ANCHORS READ ONLY ===")
rel="app/Http/Controllers/Web/ManagerInspectionWorkController.php"
source=read(rel)
for method in ("index","work","assign"):
    matches=[i for i,line in enumerate(source) if re.search(r"\bfunction\s+"+method+r"\s*\(",line)]
    if len(matches)==1:
        excerpt(rel+" @"+method,source,matches[0],90)
    else:print("METHOD",method,"COUNT",len(matches))
rel="resources/views/job-cards/show.blade.php"
source=read(rel)
tokens=["Inspection / Final Diagnosis / Technician Decision",
        "Open My Assigned Inspection","Admin: Manage Inspection Assignment",
        "inspection-manager"]
found=[i for i,line in enumerate(source) if any(x.lower() in line.lower() for x in tokens)]
print("=== JOB CARD INSPECTION PANEL ANCHORS ===")
for i in found[:12]:
    excerpt(rel+" near line "+str(i+1),source,max(0,i-6),16)
print("JOBCARD_PANEL_ANCHORS",len(found))
views=root/"resources/views"
paths=[file for file in views.rglob("*.blade.php")
       if "inspection" in file.name.lower() and "master" not in file.name.lower()]
print("=== INSPECTION VIEWS / ASSIGNMENT FORM LOCATIONS ===")
for file in paths[:35]:
    lines=file.read_bytes().decode("utf-8","surrogateescape").splitlines()
    anchors=[i for i,l in enumerate(lines)
             if re.search(r"route\(['\"]erp\.inspection-manager\.assign|assigned_staff_id|Assign Inspector|Manage Inspection Assignment",l,re.I)]
    print("VIEW",str(file.relative_to(root)),"LINES",len(lines),"ASSIGN_ANCHORS",anchors[:15])
    for line in anchors[:3]:excerpt("ASSIGNMENT VIEW CONTROL",lines,max(0,line-5),16)
print("AM207_AUDIT_COMPLETE_NO_WRITES")

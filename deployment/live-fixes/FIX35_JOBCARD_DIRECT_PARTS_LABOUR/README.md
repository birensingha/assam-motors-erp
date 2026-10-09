# FIX35 — Direct Add Parts / Labour to Job Card Without Estimate

Goal:
- Admin can add Parts and Labour/ROT directly to an existing Job Card without first creating an Estimate.
- Existing Estimate-based flow remains available and unchanged.
- Direct Part add must respect stock/inventory rules.
- Direct Labour/ROT add must reuse Labour Master / ROT assignment rules.
- Every direct add must be auditable and clearly marked as ADMIN DIRECT ADD.
- No duplicate master or stock tables.

This first step is READ-ONLY. Run INSPECT.sh against staging and use the output to prepare the exact APPLY patch.

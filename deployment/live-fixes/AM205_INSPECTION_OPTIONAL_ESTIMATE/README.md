# AM205 — Inspection Negative Findings → Optional Repair Estimate

Status: **requirements and compatibility audit only. NOT deployed, no routes/controllers/migrations active**.
This is the approved Assam Motors requirement received 2026-10-30.

## User workflow
1. Staff inspects the *assigned, version-pinned* checklist. Negative answers/remarks are saved as part of normal successful ERP inspection submission; any mandatory negative remarks are enforced.
2. Each configurable Inspection Master point determines which answer values are defects:
   - Condition Fair/Poor;
   - Working function Yes/No: No is bad;
   - CEL/leak question Yes/No: Yes is bad;
   - Availability Not Available is a defect only when configured, subject to whether missing accessories are customer-supplied vs repairable;
   - Fuel level and odometer numeric/level are **not** default defects.
3. Admin opens Inspection Findings on a Job Card. Admin can **select only eligible findings** and click **Create Optional Repair Estimate**. No automatic estimates or automatic financial posting.
4. Admin builds the estimate from Parts, Labour/ROT and OSL with taxable/GST/inclusive totals. A single defect may require several lines; each line links to the source defect, and one estimate may contain multiple defects.
5. Send item-wise proposal to customer (regular Estimate or Supplementary Estimate as appropriate). Explain price and safety criticality. Keep the existing approved main estimate and all its related records.
6. Customer gives **explicit item-wise decision**: APPROVE / DECLINE / DEFER. Decision must be saved with exact line version, decision date, actor/confirmation evidence, displayed inclusive price and any authorizing staff. Do not treat silence as approval.
7. Approved work is eligible for the existing approved **SEND TO JOB CARD** pathway; no double posting, no duplicate ROT/parts/OSL. Declined/deferred work is **not** added to job card bill.
8. Original defect remains in the existing AM189 audited defect register until actual repair + independent verification. Customer rejection cannot fake VERIFIED.
9. The currently deployed AM189 guard blocks QC PASS for all unresolved Fair/Poor conditions, even declined optional work. **Do not bypass AM189 or auto-pass on decline**. Design a separate, explicit management policy for non-safety customer-declined work, if required, and preserve safety/roadworthiness warnings; this is pending user approval.
10. If a defect is already linked to an estimate, show linked estimate number and status, and allow safe explicit revision; avoid accidental duplicates.

## Safety / privacy / audit
- Distinguish customer approval (commercial) from repair verification (physical); neither implies the other.
- No implicit repair or deletion of historical 10/20/30-point inspection findings. Only future checklist versions change.
- Signed/authorized customer acceptance, expiration and replay prevention should be designed before sending a decision link.
- The active Admin role may draft/submit proposals; enforce approval limits and authenticated checks on server for any changes.
- Do not expose customer personal data in read-only audit logs.
- Avoid retroactive changes to invoiced or closed JCs; require controlled Credit Note/reopen flow.
- UI label: "Optional Repair Recommendations" / "Send for Customer Approval". Estimate must show "Optional" distinctly from already approved estimate.
- Item-wise customer approval is not currently verified in live STAGING; **audit existing structure before coding**.

## Acceptance criteria (future)
- Negative brake pads and optional AC issue selected; admin prepares 2 recommended repair items; total including GST correct.
- Customer approves brake repair but declines AC; only approved brake work goes to Job Card and billing; AC remains original unresolved inspection finding.
- Customer declines critical brake repair: no auto QC PASS, clear safety warning and recorded decline.
- Same defect cannot create duplicate optional estimate line accidentally.
- A repaired-and-verified defect is retained in audit and becomes QC eligible under existing AM189.
- Previously completed 10/20-point reports and all current estimates/rot flows unchanged.
- Staff App form recognizes new checklist answer types only after **server dynamic submit** is compatible; otherwise retain current legacy 30-point workflow.

## First safe step
Run `audit.py` on Hostinger STAGING to enumerate existing estimate-related routes and schema without reading customer/financial row values. Then implement with backup, feature flag, migration, regression tests.

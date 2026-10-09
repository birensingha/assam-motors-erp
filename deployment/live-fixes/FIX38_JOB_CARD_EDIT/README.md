# FIX38 — Job Card Edit

Read-only discovery package for the live staging Job Card Edit workflow.

It maps:
- existing Job Card edit/update/show/index routes;
- current AdminJobCardPageController edit/update capabilities;
- Job Card header fields and schema;
- Parts/Labour schemas after FIX37;
- current list/show UI and any Edit button;
- audit support and locking markers.

No source file or database row is modified.

Run:

```bash
bash INSPECT.sh /home/u956497103/domains/assammotors.com/assam-erp-staging
```

The output is used to build the guarded apply package for:
- Edit button from Job Card list/show;
- editable header fields;
- Parts above Labour;
- direct Parts/Labour editing;
- server-side recalculation;
- audit history;
- lock after Close/Invoiced according to the live workflow.

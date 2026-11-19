#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:?ROOT}"
cd "$ROOT"; test -f artisan
echo AM195_EXISTING_CATEGORY_ROUTES
php artisan route:list --name=erp.expense-categories --columns=method,uri,name,action 2>/dev/null || php artisan route:list --name=erp.expense-categories
echo AM195_CATEGORY_ROUTE_SOURCE_LINES
grep -n -C 3 "configuration/expense-categories" routes/web.php | head -55 || true
echo AM195_CONTROLLER_FILES
find app/Http/Controllers -maxdepth 5 -iname '*expense*categor*' -print
echo AM195_CATEGORY_METHOD
python3 - <<'PY'
from pathlib import Path
import re
s=Path('app/Services/ExpensePaymentService.php').read_text()
a=s.find('function expenseCategories(')
print(s[max(0,a-180):a+1150] if a>=0 else 'MISSING')
for p in Path('app/Http/Controllers').rglob('*Expense*Categor*.php'):
 print('FILE:',str(p))
 t=p.read_text()
 for term in ['function index','function store','function update','function create','function save']:
  i=t.find(term)
  if i>=0:print(t[max(0,i-120):i+1300])
PY
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(\Illuminate\Support\Facades\DB::select("SHOW TABLES") as $r){
 $t=(string)array_values((array)$r)[0];
 if(str_contains($t,"categor") || str_contains($t,"expense")){
  echo "TABLE:$t COLUMNS:";
  echo implode(",",\Illuminate\Support\Facades\Schema::getColumnListing($t))."\\n";
 }
}
'
echo AM195_READONLY_AUDIT_COMPLETE

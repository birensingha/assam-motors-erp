<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExpenseCategoryMasterController extends Controller
{
    public function index()
    {
        return view('expenses.category-master', [
            'categories' => DB::table('workshop_expense_categories')->orderBy('name')->get()
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:140'],
            'ledger_name' => ['required','string','max:140'],
            'description' => ['nullable','string','max:1000']
        ]);
        $code = strtoupper(Str::slug(trim($data['name']), '_'));
        if (!$code || strlen($code) > 60) return back()->withErrors(['name'=>'Use a shorter category name.'])->withInput();
        if (DB::table('workshop_expense_categories')->where('code',$code)->exists())
            return back()->withErrors(['name'=>'This category already exists.'])->withInput();
        DB::table('workshop_expense_categories')->insert([
            'code'=>$code,'name'=>trim($data['name']),
            'ledger_name'=>trim($data['ledger_name']),
            'description'=>$data['description']??null,'is_active'=>1,
            'created_at'=>now(),'updated_at'=>now()
        ]);
        return redirect()->route('erp.expense-categories.index')->with('success','Category added.');
    }

    public function update(Request $request, int $category)
    {
        $data = $request->validate([
            'name'=>['required','string','max:140'],
            'ledger_name'=>['required','string','max:140'],
            'description'=>['nullable','string','max:1000'],
            'is_active'=>['required','boolean']
        ]);
        $row=DB::table('workshop_expense_categories')->where('id',$category)->first();
        abort_unless($row,404);
        DB::table('workshop_expense_categories')->where('id',$category)->update([
            'name'=>trim($data['name']),
            'ledger_name'=>trim($data['ledger_name']),
            'description'=>$data['description']??null,
            'is_active'=>(bool)$data['is_active'],'updated_at'=>now()
        ]);
        return redirect()->route('erp.expense-categories.index')->with('success','Category updated.');
    }
}

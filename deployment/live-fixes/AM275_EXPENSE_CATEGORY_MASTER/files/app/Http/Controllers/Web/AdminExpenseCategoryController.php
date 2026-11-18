<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * AM275 configuration master: add/edit/deactivate Expense Categories.
 * Never mutate category codes or any existing Expense Voucher.
 * Every change appends an audit entry; only Admin can write.
 */
final class AdminExpenseCategoryController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active &&
            $actor->isAdmin(),403,'Admin authorization required.');
        return $actor;
    }

    private function actorName(ErpAuthUser $actor): string
    {
        return mb_substr(trim((string)($actor->name?:$actor->email?:'Admin')),0,120);
    }

    public function index(Request $request): View
    {
        $this->admin($request);
        $categories=DB::table('workshop_expense_categories as c')
            ->leftJoinSub(
                DB::table('workshop_expenses')
                    ->select('expense_category')->selectRaw('COUNT(*) as uses')
                    ->groupBy('expense_category'),
                'e','e.expense_category','=','c.code'
            )->select(['c.*'])->selectRaw('COALESCE(e.uses,0) as voucher_count')
            ->orderBy('c.sort_order')->orderBy('c.display_name')->get();

        $audits=DB::table('am275_expense_category_audits as a')
            ->leftJoin('workshop_expense_categories as c','c.id','=','a.category_id')
            ->orderByDesc('a.id')->limit(70)->get(['a.*','c.code']);
        return view('expenses.categories',compact('categories','audits'));
    }

    private function normalizedCode(string $name): string
    {
        $raw=strtoupper(trim((string)\Illuminate\Support\Str::ascii($name)));
        $code=trim((string)preg_replace('/[^A-Z0-9]+/','_',$raw),'_');
        if($code===''||strlen($code)>60)
            throw ValidationException::withMessages([
                'display_name'=>'Use a category name containing letters or digits (up to 60 characters as a code).'
            ]);
        return $code;
    }

    public function store(Request $request): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate([
            'display_name'=>['required','string','min:2','max:100'],
            'ledger_group'=>['required','in:DIRECT_EXPENSES,INDIRECT_EXPENSES'],
            'reason'=>['required','string','min:8','max:400'],
        ]);
        $name=trim($data['display_name']);
        $code=$this->normalizedCode($name);
        DB::transaction(function()use($code,$name,$data,$actor){
            if(DB::table('workshop_expense_categories')->where('code',$code)->exists())
                throw ValidationException::withMessages([
                    'display_name'=>'A category with this code already exists. Edit the existing category instead.'
                ]);
            if(DB::table('workshop_expense_categories')
                ->whereRaw('LOWER(display_name)=?', [mb_strtolower($name)])->exists())
                throw ValidationException::withMessages([
                    'display_name'=>'This Expense Category name already exists.'
                ]);
            $id=DB::table('workshop_expense_categories')->insertGetId([
                'code'=>$code,'display_name'=>$name,'ledger_group'=>$data['ledger_group'],
                'is_active'=>true,'is_system'=>false,'sort_order'=>400,
                'created_by'=>$this->actorName($actor),
                'updated_by'=>$this->actorName($actor),'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('am275_expense_category_audits')->insert([
                'category_id'=>$id,'action'=>'CREATE','before_json'=>null,
                'after_json'=>json_encode([
                    'code'=>$code,'display_name'=>$name,'ledger_group'=>$data['ledger_group'],
                    'is_active'=>true,
                ],JSON_UNESCAPED_UNICODE),
                'actor'=>$this->actorName($actor),'reason'=>$data['reason'],'created_at'=>now(),
            ]);
        },3);
        return redirect()->route('erp.expense-categories.index')
            ->with('success','Expense Category '.$name.' added. It is now available in New Expense Voucher.');
    }

    public function update(Request $request,int $category): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate([
            'display_name'=>['required','string','min:2','max:100'],
            'ledger_group'=>['required','in:DIRECT_EXPENSES,INDIRECT_EXPENSES'],
            'is_active'=>['nullable','in:1'],
            'reason'=>['required','string','min:8','max:400'],
        ]);
        DB::transaction(function()use($category,$data,$actor){
            $row=DB::table('workshop_expense_categories')->where('id',$category)
                ->lockForUpdate()->first();
            abort_unless($row,404,'Expense Category not found.');
            $active=($data['is_active']??'')==='1';
            if($row->code==='OTHER'&&!$active)
                throw ValidationException::withMessages([
                    'is_active'=>'OTHER is a required default category and cannot be deactivated.'
                ]);
            $name=trim($data['display_name']);
            if(DB::table('workshop_expense_categories')
                ->where('id','<>',$row->id)
                ->whereRaw('LOWER(display_name)=?', [mb_strtolower($name)])->exists())
                throw ValidationException::withMessages([
                    'display_name'=>'This category name is already in use.'
                ]);
            $before=[
                'code'=>$row->code,'display_name'=>$row->display_name,
                'ledger_group'=>$row->ledger_group,'is_active'=>(bool)$row->is_active
            ];
            $after=[
                'code'=>$row->code,'display_name'=>$name,
                'ledger_group'=>$data['ledger_group'],'is_active'=>$active
            ];
            if($before===$after)return;
            DB::table('workshop_expense_categories')->where('id',$row->id)->update([
                'display_name'=>$name,'ledger_group'=>$data['ledger_group'],
                'is_active'=>$active,'updated_by'=>$this->actorName($actor),'updated_at'=>now()
            ]);
            DB::table('am275_expense_category_audits')->insert([
                'category_id'=>$row->id,'action'=>'UPDATE',
                'before_json'=>json_encode($before,JSON_UNESCAPED_UNICODE),
                'after_json'=>json_encode($after,JSON_UNESCAPED_UNICODE),
                'reason'=>$data['reason'],'actor'=>$this->actorName($actor),
                'created_at'=>now(),
            ]);
        },3);
        return redirect()->route('erp.expense-categories.index')
            ->with('success','Expense Category updated. Historical voucher category codes remain unchanged.');
    }
}

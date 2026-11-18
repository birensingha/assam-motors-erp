<?php
declare(strict_types=1);
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use App\Models\ErpAuthUser;

/**
 * AM275_EXPENSE_CATEGORY_MASTER:
 * - master code is an immutable accounting/reporting key
 * - display label may be edited; existing expenses retain their original key
 * - inactive categories stay in historical registers but cannot be used anew
 * - audit keeps before/after and actor; no hard deletes
 */
final class ExpenseCategoryMasterService
{
    public static function activeCodes():array {
        return DB::table('am275_expense_categories')
            ->where('is_active',1)->orderBy('sort_order')->orderBy('display_name')
            ->pluck('code')->all();
    }

    public static function allCodes():array {
        return DB::table('am275_expense_categories')
            ->orderBy('sort_order')->orderBy('display_name')
            ->pluck('code')->all();
    }

    public static function labels():array {
        return DB::table('am275_expense_categories')
            ->pluck('display_name','code')->all();
    }

    public static function requireActive(string $code):void {
        if($code===''||!DB::table('am275_expense_categories')
            ->where('code',$code)->where('is_active',1)->exists())
            throw ValidationException::withMessages([
                'expense_category'=>'Select an active Expense Category from Settings. This category may have been disabled.'
            ]);
    }

    private function actor(Request $request):ErpAuthUser {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),
            403,'Admin permission required for Expense Category Settings.');
        return $user;
    }

    private function who(ErpAuthUser $actor):string {
        return mb_substr(trim((string)($actor->email ?: $actor->name ?: 'ADMIN')),0,160);
    }

    public function listing(Request $request):array {
        $this->actor($request);
        $usage=DB::table('workshop_expenses')->select('expense_category')
            ->selectRaw('COUNT(*) AS entries')
            ->groupBy('expense_category')->pluck('entries','expense_category');
        return [
            'categories'=>DB::table('am275_expense_categories')
                ->orderBy('sort_order')->orderBy('display_name')->get(),
            'usage'=>$usage,
            'history'=>DB::table('am275_expense_category_audits')
                ->orderByDesc('id')->limit(30)->get()
        ];
    }

    public function add(Request $request):string {
        $actor=$this->actor($request);
        $input=$request->validate([
            'display_name'=>['required','string','min:2','max:100'],
            'reason'=>['required','string','min:8','max:300']
        ]);
        $label=trim((string)$input['display_name']);
        $code=trim((string)preg_replace('/[^A-Z0-9]+/','_',strtoupper($label)),'_');
        if(strlen($code)>60||strlen($code)<2)
            throw ValidationException::withMessages([
                'display_name'=>'Category Name must produce a valid code (2–60 letters/numbers).'
            ]);
        if(!preg_match('/^[A-Z][A-Z0-9_]*$/',$code))
            throw ValidationException::withMessages([
                'display_name'=>'Start category name with a letter.'
            ]);
        return DB::transaction(function()use($code,$label,$input,$actor){
            $exists=DB::table('am275_expense_categories')->where('code',$code)
                ->lockForUpdate()->exists();
            if($exists)throw ValidationException::withMessages([
                'display_name'=>'This category already exists. Use Edit Category to rename or activate it.'
            ]);
            $id=DB::table('am275_expense_categories')->insertGetId([
                'code'=>$code,'display_name'=>$label,'is_active'=>1,'is_system'=>0,
                'sort_order'=>100+(int)DB::table('am275_expense_categories')->max('sort_order'),
                'created_by'=>$this->who($actor),'created_at'=>now(),'updated_at'=>now()
            ]);
            $after=(array)DB::table('am275_expense_categories')->where('id',$id)->first();
            DB::table('am275_expense_category_audits')->insert([
                'category_id'=>$id,'category_code'=>$code,'action'=>'CREATE',
                'before_json'=>null,'after_json'=>json_encode($after),
                'actor'=>$this->who($actor),'reason'=>$input['reason'],
                'created_at'=>now(),'updated_at'=>now()
            ]);
            return $code;
        },3);
    }

    public function edit(Request $request,int $categoryId):string {
        $actor=$this->actor($request);
        $input=$request->validate([
            'display_name'=>['required','string','min:2','max:100'],
            'is_active'=>['required','in:0,1'],
            'reason'=>['required','string','min:8','max:300']
        ]);
        return DB::transaction(function()use($categoryId,$input,$actor){
            $category=DB::table('am275_expense_categories')->where('id',$categoryId)
                ->lockForUpdate()->first();
            if(!$category)abort(404,'Expense Category not found.');
            $label=trim((string)$input['display_name']);
            $active=(int)$input['is_active'];
            // Keep special workflows from accidentally disappearing.
            if($category->code==='LUBRICASH_COUPON' && $active===0)
                throw ValidationException::withMessages(['is_active'=>
                    'Lubricash Coupon is a protected Staff/Job Card workflow. It cannot be disabled here.']);
            if($category->code==='SALARY_WAGES' && $active===1 && (int)$category->is_active===0)
                throw ValidationException::withMessages(['is_active'=>
                    'Salary must be recorded using the Staff Financial Ledger, not Expense Voucher.']);
            if($label===(string)$category->display_name && $active===(int)$category->is_active)
                return (string)$category->code;
            $before=(array)$category;
            DB::table('am275_expense_categories')->where('id',$categoryId)->update([
                'display_name'=>$label,'is_active'=>$active,'updated_at'=>now()
            ]);
            $after=(array)DB::table('am275_expense_categories')->where('id',$categoryId)->first();
            DB::table('am275_expense_category_audits')->insert([
                'category_id'=>$categoryId,'category_code'=>$category->code,
                'action'=>$active!==(int)$category->is_active?'STATUS':'RENAME',
                'before_json'=>json_encode($before),'after_json'=>json_encode($after),
                'actor'=>$this->who($actor),'reason'=>$input['reason'],
                'created_at'=>now(),'updated_at'=>now()
            ]);
            return (string)$category->code;
        },3);
    }
}

<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * AM259: Separate read-only invoiced Labour/ROT and OSL Sales Registers.
 * Service types are mutually exclusive; OSL is a job_card_labour record
 * carrying mechanic_name OSL or an OSL-prefixed service/master code.
 *
 * Only the most recent non-reversed invoice per Job Card is considered to
 * prevent double-counting when old invoices were reversed and reissued.
 * Revenue is from CURRENT JC billing lines, not an immutable invoice snapshot.
 * Category-specific credit-note allocation is not available and is NOT
 * inferred/subtracted. Never describe this as audited P&L.
 */
final class AdminServiceSalesRegisterController extends Controller
{
    private function authorise(Request $request): void
    {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active && $actor->isAdmin(),
            403,'Admin permission required to view service sales.');
        foreach (['invoices','job_cards','job_card_labour','invoice_credit_notes'] as $table) {
            abort_unless(Schema::hasTable($table),503,'Service Sales Register source missing: '.$table);
        }
    }

    private function oslCondition($query): void
    {
        $query->where(static function($q): void {
            $q->whereRaw("UPPER(TRIM(COALESCE(l.mechanic_name,''))) = 'OSL'")
              ->orWhereRaw("UPPER(TRIM(COALESCE(l.rot_code,''))) LIKE 'OSL%'");
        });
    }

    private function labourCondition($query): void
    {
        $query->where(static function($q): void {
            $q->whereRaw("UPPER(TRIM(COALESCE(l.mechanic_name,''))) <> 'OSL'")
              ->whereRaw("UPPER(TRIM(COALESCE(l.rot_code,''))) NOT LIKE 'OSL%'");
        });
    }

    public function labour(Request $request): View
    {
        return $this->register($request,'labour');
    }

    public function osl(Request $request): View
    {
        return $this->register($request,'osl');
    }

    private function register(Request $request,string $category): View
    {
        $this->authorise($request);
        $filters=$request->validate([
            'q'=>['nullable','string','max:120'],
            'from'=>['nullable','date_format:Y-m-d'],
            'to'=>['nullable','date_format:Y-m-d','after_or_equal:from'],
        ]);
        $search=trim((string)($filters['q']??''));
        $from=(string)($filters['from']??'');
        $to=(string)($filters['to']??'');

        $latestInvoice=DB::table('invoices')
            ->selectRaw('MAX(id) as active_invoice_id')
            ->whereNotNull('job_card_id')
            ->whereRaw("LOWER(COALESCE(status,'')) NOT IN ('reversed','void','cancelled')")
            ->groupBy('job_card_id');

        $source=DB::table('job_card_labour as l')
            ->join('invoices as i','i.job_card_id','=','l.job_card_id')
            ->joinSub($latestInvoice,'active_invoice',static function($join):void{
                $join->on('active_invoice.active_invoice_id','=','i.id');
            })
            ->leftJoin('job_cards as jc','jc.id','=','i.job_card_id')
            ->where(static function($q):void{
                $q->whereNull('l.billing_included')
                  ->orWhere('l.billing_included',1);
            });
        if($category==='osl')$this->oslCondition($source);
        else $this->labourCondition($source);

        if($from!=='')$source->whereDate('i.invoice_date','>=',$from);
        if($to!=='')$source->whereDate('i.invoice_date','<=',$to);
        if($search!==''){
            $like='%'.str_replace(['%','_'],['\\%','\\_'],$search).'%';
            $source->where(static function($q)use($like):void{
                $q->where('i.invoice_no','like',$like)
                  ->orWhere('i.customer_name','like',$like)
                  ->orWhere('i.vehicle_reg_no','like',$like)
                  ->orWhere('jc.job_no','like',$like)
                  ->orWhere('l.rot_code','like',$like)
                  ->orWhere('l.description','like',$like);
            });
        }

        $summary=(clone $source)->selectRaw(
            'COUNT(*) as item_count, COUNT(DISTINCT i.id) as invoice_count, '.
            'COALESCE(SUM(COALESCE(l.total,0)),0) as net_sales, '.
            'COALESCE(SUM(COALESCE(l.discount_amount,0)),0) as discounts'
        )->first();
        $summary->gross_before_discount=round((float)$summary->net_sales+(float)$summary->discounts,2);

        $rows=(clone $source)
            ->orderByDesc('i.invoice_date')
            ->orderByDesc('i.id')
            ->orderByDesc('l.id')
            ->select([
                'l.id as line_id','l.rot_code','l.description','l.standard_hours',
                'l.labour_rate','l.discount_percent','l.discount_amount',
                'l.total','l.gst_percent','l.mechanic_name',
                'i.id as invoice_id','i.invoice_no','i.invoice_date','i.status as invoice_status',
                'i.customer_name','i.vehicle_reg_no','jc.id as job_card_id','jc.job_no'
            ])
            ->paginate(50)->withQueryString();

        // Posted credit notes have no trustworthy Labour vs OSL amount split.
        // Count these separately without allocating them to either register.
        $creditNotes=DB::table('invoice_credit_notes as c')
            ->join('invoices as i','i.id','=','c.invoice_id')
            ->whereRaw("LOWER(COALESCE(c.status,''))='posted'")
            ->whereRaw("LOWER(COALESCE(i.status,'')) NOT IN ('reversed','void','cancelled')");
        if($from!=='')$creditNotes->whereDate('c.credit_date','>=',$from);
        if($to!=='')$creditNotes->whereDate('c.credit_date','<=',$to);
        $unallocatedCreditCount=(int)$creditNotes->count();

        return view('sales-register.service-index',compact(
            'rows','summary','search','from','to','category','unallocatedCreditCount'
        ));
    }
}

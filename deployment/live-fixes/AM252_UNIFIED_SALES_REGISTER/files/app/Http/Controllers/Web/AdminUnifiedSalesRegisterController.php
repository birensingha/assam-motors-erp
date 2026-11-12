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
 * AM252: read-only unified Sales Register.
 * Includes the original positive invoice and its separate negative posted Credit Note.
 * No financial posting, tax recalculation or row modification.
 */
final class AdminUnifiedSalesRegisterController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active && $actor->isAdmin(),
            403, 'Admin / Supervisor permission required');

        foreach (['invoices', 'invoice_credit_notes'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Sales Register source not ready: '.$table);
        }

        $filters = $request->validate([
            'q' => ['nullable','string','max:120'],
            'type' => ['nullable','in:all,sale,credit_note'],
            'from' => ['nullable','date_format:Y-m-d'],
            'to' => ['nullable','date_format:Y-m-d','after_or_equal:from'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $type = (string) ($filters['type'] ?? 'all');
        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';

        $sales = DB::table('invoices as i')
            ->selectRaw("'SALE' as document_type, i.id as doc_id, i.invoice_no as document_no, i.invoice_date as document_date,
                         i.customer_name as customer_name, i.vehicle_reg_no as vehicle_no,
                         i.id as invoice_id, i.invoice_no as invoice_no,
                         i.grand_total as signed_amount, i.gst_total as signed_gst,
                         i.status as document_status, i.created_at as created_at");

        $credits = DB::table('invoice_credit_notes as c')
            ->leftJoin('invoices as i', 'i.id', '=', 'c.invoice_id')
            ->where('c.status', 'posted')
            ->selectRaw("'CREDIT_NOTE' as document_type, c.id as doc_id, c.credit_note_no as document_no, c.credit_date as document_date,
                         i.customer_name as customer_name, i.vehicle_reg_no as vehicle_no,
                         c.invoice_id as invoice_id, i.invoice_no as invoice_no,
                         -c.credit_amount as signed_amount, -c.gst_amount as signed_gst,
                         c.status as document_status, c.created_at as created_at");

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $sales->where(static function ($q) use ($like): void {
                $q->where('i.invoice_no','like',$like)
                  ->orWhere('i.customer_name','like',$like)
                  ->orWhere('i.vehicle_reg_no','like',$like);
            });
            $credits->where(static function ($q) use ($like): void {
                $q->where('c.credit_note_no','like',$like)
                  ->orWhere('i.invoice_no','like',$like)
                  ->orWhere('i.customer_name','like',$like)
                  ->orWhere('i.vehicle_reg_no','like',$like);
            });
        }
        if ($from !== '') {
            $sales->whereDate('i.invoice_date','>=',$from);
            $credits->whereDate('c.credit_date','>=',$from);
        }
        if ($to !== '') {
            $sales->whereDate('i.invoice_date','<=',$to);
            $credits->whereDate('c.credit_date','<=',$to);
        }

        // Keep one continuous register; use selection filter only when explicitly requested.
        if ($type === 'sale') {
            $source = $sales;
        } elseif ($type === 'credit_note') {
            $source = $credits;
        } else {
            $source = $sales->unionAll($credits);
        }

        $register = DB::query()->fromSub($source,'docs');
        $summary = (clone $register)
            ->selectRaw("COUNT(*) as document_count,
                COALESCE(SUM(CASE WHEN document_type='SALE' THEN signed_amount ELSE 0 END),0) as sales_total,
                COALESCE(SUM(CASE WHEN document_type='CREDIT_NOTE' THEN -signed_amount ELSE 0 END),0) as credit_total,
                COALESCE(SUM(signed_amount),0) as net_total")
            ->first();
        $rows = (clone $register)->orderByDesc('document_date')
            ->orderByDesc('created_at')->orderByDesc('doc_id')->paginate(40)->withQueryString();

        return view('sales-register.index',compact('rows','summary','search','type','from','to'));
    }
}

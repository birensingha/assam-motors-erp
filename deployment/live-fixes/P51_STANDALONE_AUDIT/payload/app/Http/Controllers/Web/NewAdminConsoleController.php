<?php
declare(strict_types=1);
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P51 Phase 1: New staging-native Admin Console (GET-only).
 * Deliberately does NOT call legacy endpoints or legacy databases.
 * All module mutations stay guarded by their existing ERP controllers.
 */
final class NewAdminConsoleController extends Controller {
    public function dashboard(Request $request) {
        $user=$request->user();
        if(!($user instanceof ErpAuthUser)
           || !(bool)$user->active
           || strtolower(trim((string)$user->role))!=='admin') {
            abort(403,'Only the active NEW ERP Admin can open NEW Admin Console.');
        }
        $tables=[
            'Customers'=>'customer_master',
            'Vehicles'=>'am_vehicle_master',
            'Parts Master'=>'parts_master',
            'Job Cards'=>'job_cards',
            'Invoices'=>'invoices',
            'Vendors'=>'workshop_vendors',
            'Staff'=>'staff',
            'Payments'=>'workshop_payment_vouchers',
        ];
        $stats=[];
        foreach($tables as $label=>$table){
            try {
                $stats[$label]=Schema::hasTable($table)
                    ? DB::table($table)->count()
                    : null;
            } catch(\Throwable $e) {
                $stats[$label]=null; // Graceful display; do not expose SQL to UI.
            }
        }
        return view('admin.new-console',compact('stats'));
    }
}

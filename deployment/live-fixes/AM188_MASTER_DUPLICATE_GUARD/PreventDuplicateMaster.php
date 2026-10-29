<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** AM188 — prevent duplicate native Vendor/Customer CREATE requests. */
final class PreventDuplicateMaster
{
    private const TABLES = [
        'vendor'=>['workshop_vendors'],
        'customer'=>['am_erp_customers','customer_master','customers'],
    ];
    private const ALIASES = [
        'vendor'=>[
            'name'=>['vendor_name','name','supplier_name'],
            'phone'=>['mobile','phone','phone_no','contact_no'],
            'gst'=>['gstin','gst_no','gst_number']],
        'customer'=>[
            'name'=>['customer_name','name','full_name'],
            'phone'=>['mobile','phone','mobile_no','phone_no','phone_number','contact_no'],
            'gst'=>['gstin','gst_no','gst_number']],
    ];
    private static function pick(array $data, array $aliases): string
    {
        foreach ([$data,$data['customer']??[],$data['vendor']??[]] as $row) {
            if (!is_array($row)) continue;
            foreach ($aliases as $key) {
                if (isset($row[$key]) && is_scalar($row[$key])) {
                    $value=trim((string)$row[$key]);
                    if ($value!=='') return $value;
                }
            }
        }
        return '';
    }
    private static function normName(string $value): string
    {
        $value=mb_strtolower(trim($value));
        return preg_replace('/[\s\p{P}\p{Z}]+/u','',$value)??$value;
    }
    private static function phone(string $value): string
    {
        $value=preg_replace('/\D/','',$value)??'';
        return strlen($value)===12 && str_starts_with($value,'91')?substr($value,2):$value;
    }
    private static function col(array $columns, array $options): ?string
    {
        foreach ($options as $opt) if (in_array($opt,$columns,true))return $opt;
        return null;
    }
    private static function q(string $column): string
    {
        return chr(96).str_replace(chr(96),chr(96).chr(96),$column).chr(96);
    }
    private static function exists(string $type, string $name, string $phone, string $gst): ?array
    {
        $name=self::normName($name);
        $phone=self::phone($phone);
        $gst=strtoupper(preg_replace('/\s+/','',$gst)??'');
        $supported=false;
        foreach (self::TABLES[$type] as $table) {
            if (!Schema::hasTable($table))continue;
            $cols=Schema::getColumnListing($table);
            $nc=self::col($cols,self::ALIASES[$type]['name']);
            $pc=self::col($cols,self::ALIASES[$type]['phone']);
            $gc=self::col($cols,self::ALIASES[$type]['gst']);
            $id=self::col($cols,['id','customer_id','vendor_id']);
            if (!$nc)continue;
            $supported=true;
            $useName=$name!=='';
            $usePhone=$phone!=='' && strlen($phone)===10 && (bool)$pc;
            $useGst=$gst!=='' && (bool)$gc;
            if (!$useName && !$useGst)continue;
            $n=self::q($nc);
            $query=DB::table($table)->select(array_values(array_unique(
                array_filter([$nc,$pc,$gc,$id])
            )));
            $query->where(function ($sub)use($type,$useName,$usePhone,$useGst,$name,$phone,$gst,$n,$pc,$gc):void{
                $nameSql="LOWER(REGEXP_REPLACE(COALESCE($n,''),'[[:space:][:punct:]]+','')) = ?";
                if ($useName && $type==='vendor')$sub->orWhereRaw($nameSql,[$name]);
                if ($type==='customer') {
                    // Same Indian mobile is the strongest Customer identity:
                    // reject even when a name is misspelled differently.
                    if ($usePhone) {
                        $p=self::q($pc);
                        $sub->orWhereRaw(
                            "RIGHT(REGEXP_REPLACE(COALESCE($p,''),'[^0-9]',''),10) = ?",
                            [$phone]
                        );
                    } elseif ($useName && !$useGst) {
                        // For customers without mobile/GSTIN, block an exact
                        // normalized-name duplicate to prevent accidental copies.
                        $sub->orWhereRaw($nameSql,[$name]);
                    }
                }
                if ($useGst) {
                    $g=self::q($gc);
                    $sub->orWhereRaw("UPPER(REPLACE(COALESCE($g,''),' ','')) = ?",[$gst]);
                }
            });
            $row=$query->first();
            if ($row)return [
                'name'=>(string)($row->{$nc}??''),
                'id'=>$id?(string)($row->{$id}??''):'',
            ];
        }
        if (!$supported)throw new \RuntimeException("AM188 $type table or name column missing");
        return null;
    }
    public function handle(Request $request, Closure $next, string $type): Response
    {
        if (!isset(self::TABLES[$type]))throw new \InvalidArgumentException('Unknown master');
        if ($request->method()!=='POST')return $next($request);
        $data=$request->all();
        $name=self::pick($data,self::ALIASES[$type]['name']);
        $phone=self::pick($data,self::ALIASES[$type]['phone']);
        $gst=self::pick($data,self::ALIASES[$type]['gst']);
        if ($name==='' && $phone==='' && $gst==='')return $next($request);

        // Serializes guarded creates, preventing two simultaneous duplicate saves.
        $lock="am188_master_create_$type";
        $held=DB::selectOne('SELECT GET_LOCK(?,10) AS locked',[$lock]);
        if (!$held || (int)$held->locked!==1)
            throw ValidationException::withMessages(['duplicate'=>'Another save is in progress. Please retry.']);
        try {
            $found=self::exists($type,$name,$phone,$gst);
            if ($found) {
                $label=ucfirst($type);
                $id=$found['id']!==''?' (#'.$found['id'].')':'';
                throw ValidationException::withMessages([
                    $type==='vendor'?'vendor_name':'customer_name'=>
                    "Duplicate $label blocked: {$found['name']}$id already exists. Open/edit the existing master instead."
                ]);
            }
            return $next($request);
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)',[$lock]);
        }
    }
}

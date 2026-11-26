<?php
/* Pure PHP safety tests for the Hostinger adapter; no Laravel/customer data. */
namespace App\Models {
    class ErpAuthUser {
        public function __construct(public bool $active = true, public string $role = 'admin') {}
    }
}
namespace Illuminate\Support\Facades {
    class Auth {
        public static ?object $user = null;
        public static function guard(string $guard): object {
            if ($guard !== 'web') throw new \RuntimeException('wrong auth guard');
            return new class { public function user(): ?object { return Auth::$user; } };
        }
    }
    class Schema {
        public static bool $consentColumnPresent = false;
        public static function hasColumn(string $table, string $column): bool {
            if ($table !== 'am_customer_master' || $column !== 'whatsapp_opt_in_at')
                throw new \RuntimeException('unverified consent column');
            return self::$consentColumnPresent;
        }
    }
    class DB {
        public static ?object $job = null;
        public static bool $consent = false;
        public static int $calls = 0;
        public static function table(string $table): FakeQuery {
            self::$calls++;
            if (!in_array($table, ['job_cards as j', 'am_customer_master'], true))
                throw new \RuntimeException('unexpected DB access');
            return new FakeQuery($table);
        }
    }
    class FakeQuery {
        public function __construct(private string $table) {}
        public function join(string $table, ...$args): self { return $this; }
        public function on(...$args): self { return $this; }
        public function where(...$args): self { return $this; }
        public function whereNotNull(string $field): self { return $this; }
        public function first(array $fields = []): ?object { return DB::$job; }
        public function exists(): bool { return DB::$consent; }
    }
}
namespace {
    use App\Models\ErpAuthUser;
    use App\Support\Am245HostingerFeedbackInvitationSource;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $flags = ['am245.verified_single_company_scope'=>false,
              'am245.verified_customer_opt_in_column'=>null];
    function config(string $key, mixed $default = null): mixed {
        global $flags;
        return $flags[$key] ?? $default;
    }
    function check(bool $ok, string $reason): void {
        if (!$ok) throw new \RuntimeException($reason);
    }
    require_once __DIR__.'/../app/Contracts/AmFeedbackInvitationSource.php';
    require_once __DIR__.'/../app/Support/Am245HostingerFeedbackInvitationSource.php';

    $source = new Am245HostingerFeedbackInvitationSource();
    Auth::$user = new ErpAuthUser();
    DB::$job = (object)[
        'customer_id'=>11,'customer_name'=>'TEST CUSTOMER',
        'vehicle_id'=>12,'vehicle'=>'TEST-0000','job_card_id'=>13,
        'job_card_ref'=>'JC-TEST-0000','phone'=>'9999999999',
        'status'=>'completed','customer_public_status'=>'Quality Check Passed',
    ];
    check($source->forJobCard(13) === null, 'Scope unverified must fail closed');
    check(DB::$calls === 0, 'Do not query DB before verifying scope');
    $flags['am245.verified_single_company_scope'] = true;
    Auth::$user = new ErpAuthUser(true,'supervisor');
    check($source->forJobCard(13) === null, 'Supervisor must be denied');
    Auth::$user = new ErpAuthUser(false,'admin');
    check($source->forJobCard(13) === null, 'Inactive Admin must be denied');
    Auth::$user = new ErpAuthUser();
    $x = $source->forJobCard(13);
    check($x['service_finished'] === true, 'QC pass completed job must qualify');
    check($x['messaging_permitted'] === false, 'No opt-in data must deny WhatsApp');
    $flags['am245.verified_customer_opt_in_column'] = 'whatsapp_opt_in_at';
    check($source->forJobCard(13)['messaging_permitted'] === false,
        'Missing consent column must deny');
    Schema::$consentColumnPresent = true;
    DB::$consent = true;
    check($source->forJobCard(13)['messaging_permitted'] === true,
        'Verified customer opt-in permits compose');
    DB::$consent = false;
    check($source->forJobCard(13)['messaging_permitted'] === false,
        'Customer who has not opted in must be denied');
    DB::$job->status = 'in-progress';
    DB::$job->customer_public_status = 'Work in progress';
    check($source->forJobCard(13)['service_finished'] === false,
        'Unfinished Job Card must be denied');
    DB::$job->status = 'completed';
    DB::$job->customer_public_status = 'Work Complete / Awaiting QC';
    check($source->forJobCard(13)['service_finished'] === false,
        'Completed without QC pass must be denied');
    DB::$job->customer_public_status = 'Ready for Delivery';
    check($source->forJobCard(13)['service_finished'] === true,
        'Ready for Delivery should qualify');
    DB::$job->status = 'invoiced';
    check($source->forJobCard(13)['service_finished'] === true,
        'Invoiced Job Card should qualify');
    echo "Hostinger invitation adapter fail-closed checks PASSED\n";
}

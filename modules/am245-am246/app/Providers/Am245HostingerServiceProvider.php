<?php
namespace App\Providers;

use App\Contracts\AmFeedbackCustomerSource;
use App\Contracts\AmFeedbackInvitationSource;
use App\Models\ErpAuthUser;
use App\Support\Am245HostingerFeedbackCustomerSource;
use App\Support\Am245HostingerFeedbackInvitationSource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * AM245-only bindings for the inspected Hostinger ERP layout.
 * Register explicitly in bootstrap/providers.php only after reviewing the
 * real deployed app. Does not bind AM246 finance or enable deployment.
 */
final class Am245HostingerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AmFeedbackCustomerSource::class,
            Am245HostingerFeedbackCustomerSource::class
        );
        $this->app->bind(
            AmFeedbackInvitationSource::class,
            Am245HostingerFeedbackInvitationSource::class
        );
    }

    public function boot(): void
    {
        // The inspected ERP has ErpAuthUser::isAdmin() that includes supervisor.
        // Feedback management is deliberately restricted to the admin role.
        // Any existing Gate implementation must be reviewed before activation.
        if (!Gate::has('manage-customer-engagement')) {
            Gate::define('manage-customer-engagement', static function ($user): bool {
                return $user instanceof ErpAuthUser
                    && (bool) $user->active
                    && strtolower((string) $user->role) === 'admin';
            });
        }
    }
}

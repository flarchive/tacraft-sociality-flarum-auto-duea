<?php

namespace TacraftSociality\AutoDuea;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Finds and removes accounts whose email address was never confirmed.
 *
 * Accounts that have confirmed their email are never touched, and neither are
 * administrators (the root admin, members of the built-in administrator group,
 * or anything with an id of 1).
 */
class AutoDuea
{
    public const ENABLED = 'auto_duea_enabled';
    public const INTERVAL_HOURS = 'auto_duea_interval_hours';
    public const GRACE_DAYS = 'auto_duea_grace_days';
    public const BATCH = 'auto_duea_batch';
    public const LAST_RUN = 'auto_duea_last_run';
    public const LAST_DELETED = 'auto_duea_last_deleted';
    public const DELETED_TOTAL = 'auto_duea_deleted_total';

    public const ADMIN_GROUP_ID = 1;
    public const MAX_BATCH = 500;
    public const MAX_TOTAL = 20000;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::ENABLED, false);
    }

    public function intervalHours(): int
    {
        return max(1, (int) $this->settings->get(self::INTERVAL_HOURS, 24));
    }

    public function graceDays(): int
    {
        return max(0, (int) $this->settings->get(self::GRACE_DAYS, 7));
    }

    public function batch(): int
    {
        return min(self::MAX_BATCH, max(1, (int) $this->settings->get(self::BATCH, 100)));
    }

    public function lastRun(): ?Carbon
    {
        $value = $this->settings->get(self::LAST_RUN);

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Every account that has not confirmed its email and is not an administrator.
     */
    public function pendingQuery(): Builder
    {
        return User::query()
            ->where('is_email_confirmed', false)
            ->where('id', '>', 1)
            ->whereDoesntHave('groups', function (Builder $query) {
                $query->where('id', self::ADMIN_GROUP_ID);
            });
    }

    /**
     * Accounts that also joined longer ago than the grace period.
     */
    public function eligibleQuery(): Builder
    {
        return $this->pendingQuery()
            ->whereNotNull('joined_at')
            ->where('joined_at', '<=', Carbon::now()->subDays($this->graceDays()));
    }

    public function counts(): array
    {
        return [
            'pending' => $this->pendingQuery()->count(),
            'eligible' => $this->eligibleQuery()->count(),
            'deletedTotal' => (int) $this->settings->get(self::DELETED_TOTAL, 0),
            'lastDeleted' => (int) $this->settings->get(self::LAST_DELETED, 0),
            'lastRun' => $this->lastRun()?->toIso8601String(),
            'enabled' => $this->enabled(),
            'intervalHours' => $this->intervalHours(),
            'graceDays' => $this->graceDays(),
            'batch' => $this->batch(),
        ];
    }

    /**
     * Delete up to $limit eligible accounts. Returns how many were removed.
     */
    public function run(?int $limit = null): int
    {
        $limit = min(self::MAX_BATCH, max(1, $limit ?? $this->batch()));

        $deleted = 0;

        $users = $this->eligibleQuery()
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($users as $user) {
            try {
                $user->delete();
                $deleted++;
            } catch (Throwable $e) {
                $this->log('error', 'Could not delete an unconfirmed account', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($deleted > 0) {
            $this->markRun($deleted);
        }

        return $deleted;
    }

    /**
     * Delete every eligible account, in batches, up to a sane upper limit.
     */
    public function runAll(int $cap = self::MAX_TOTAL): int
    {
        $total = 0;

        while ($total < $cap) {
            $deleted = $this->run();

            if ($deleted === 0) {
                break;
            }

            $total += $deleted;
        }

        return $total;
    }

    /**
     * Run a sweep if automatic deletion is enabled and the interval has elapsed.
     */
    public function runIfDue(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $lastRun = $this->lastRun();

        if ($lastRun !== null && $lastRun->diffInMinutes(Carbon::now(), true) < $this->intervalHours() * 60) {
            return 0;
        }

        return $this->runAll();
    }

    protected function markRun(int $deleted): void
    {
        $this->settings->set(self::LAST_RUN, Carbon::now()->toIso8601String());
        $this->settings->set(self::LAST_DELETED, (string) $deleted);
        $this->settings->set(
            self::DELETED_TOTAL,
            (string) ((int) $this->settings->get(self::DELETED_TOTAL, 0) + $deleted)
        );
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        try {
            $logger = resolve('log');

            if (method_exists($logger, $level)) {
                $logger->{$level}('[auto-duea] '.$message, $context);
            }
        } catch (Throwable) {
            // Logging must never break the sweep.
        }
    }
}

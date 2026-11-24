<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records every outbound consumer notification so a lifecycle event can never
 * be emailed twice, and so failures leave a diagnosable trail.
 */
class ConsumerNotificationLog extends Model
{
    public const EVENT_CONNECTION_APPROVED = 'connection_approved';

    public const EVENT_CONNECTION_INSTALLATION_CONFIRMED = 'connection_installation_confirmed';

    public const EVENT_CONNECTION_REJECTED = 'connection_rejected';

    public const EVENT_CONNECTION_REQUIREMENTS_REQUESTED = 'connection_requirements_requested';

    public const EVENT_CREDENTIALS_RESENT = 'credentials_resent';

    public const EVENT_PASSWORD_TEMPORARY = 'password_temporary';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * Outcome of a send attempt.
     */
    public const RESULT_SENT = 'sent';

    public const RESULT_PENDING = 'pending';

    public const RESULT_SUPPRESSED = 'suppressed';

    public const RESULT_FAILED = 'failed';

    protected $fillable = [
        'consumer_id',
        'channel',
        'event',
        'recipient',
        'status',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public static function alreadySent(int $consumerId, string $event): bool
    {
        return static::query()
            ->where('consumer_id', $consumerId)
            ->where('event', $event)
            ->where('status', self::STATUS_SENT)
            ->exists();
    }

    /**
     * Sends the mail at most once per (consumer, event) and records the outcome.
     *
     * Returns RESULT_SENT, RESULT_SUPPRESSED (a previous submission already succeeded)
     * or RESULT_FAILED.
     *
     * A previously failed attempt does not block a retry: the log row is updated
     * in place so a later success simply flips its status.
     *
     * @param  callable(): void  $send
     */
    public static function sendOnce(int $consumerId, string $event, ?string $recipient, callable $send, bool $force = false): string
    {
        if (! $force && static::alreadySent($consumerId, $event)) {
            return self::RESULT_SUPPRESSED;
        }

        try {
            $send();

            static::record($consumerId, $event, $recipient, self::STATUS_SENT, null);

            return self::RESULT_SENT;
        } catch (Throwable $exception) {
            Log::error('Consumer notification could not be sent.', [
                'consumer_id' => $consumerId,
                'event' => $event,
                'recipient' => $recipient,
                'mailer' => config('mail.default'),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            static::record($consumerId, $event, $recipient, self::STATUS_FAILED, $exception->getMessage());

            return self::RESULT_FAILED;
        }
    }

    /**
     * Keeps exactly one row per (consumer, event) so a retry after a failure
     * updates the existing record instead of colliding with it.
     */
    private static function record(int $consumerId, string $event, ?string $recipient, string $status, ?string $errorMessage): void
    {
        static::query()->updateOrCreate(
            ['consumer_id' => $consumerId, 'event' => $event],
            [
                'channel' => 'email',
                'recipient' => $recipient,
                'status' => $status,
                'error_message' => $errorMessage,
                'sent_at' => $status === self::STATUS_SENT ? now() : null,
            ],
        );
    }
}

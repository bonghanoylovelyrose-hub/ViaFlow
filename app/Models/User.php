<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'role',
        'password',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'must_change_reactivation_password' => 'boolean',
            'remember_expires_at' => 'datetime',
        ];
    }

    /** Reject remembered logins after their server-side expiration. */
    public function getRememberToken()
    {
        if (! $this->remember_expires_at || $this->remember_expires_at->isPast()) {
            return null;
        }

        return parent::getRememberToken();
    }

    public function consumer(): HasOne
    {
        return $this->hasOne(Consumer::class);
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'reader_id');
    }

    public function verifiedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'verified_by');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'applied_by_user_id');
    }

    public function loanRepayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class, 'recorded_by_user_id');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isConsumer(): bool
    {
        return $this->role === 'consumer';
    }

    public function isMeterReader(): bool
    {
        return in_array($this->role, ['staff', 'meter_reader'], true);
    }

    public function requiresPasswordChange(): bool
    {
        return $this->isConsumer() && $this->must_change_password;
    }

    public static function generateUniqueUsername(string $name): string
    {
        $firstName = preg_split('/\s+/', trim($name))[0] ?? '';
        $base = Str::lower(Str::slug($firstName, ''));
        $base = $base !== '' ? $base : 'consumer';
        $username = $base;
        $suffix = 2;

        while (self::query()->where('username', $username)->exists()) {
            $username = $base.$suffix;
            $suffix++;
        }

        return $username;
    }
}

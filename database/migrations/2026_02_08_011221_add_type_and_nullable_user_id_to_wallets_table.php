<?php

declare(strict_types=1);

use App\Enums\WalletType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PLATFORM_USER_EMAIL = 'platform@internal.settlement';

    /**
     * Add wallet type (customer | platform) for profit settlement.
     * Platform wallet uses a dedicated system user for SQLite compatibility.
     */
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->string('type', 20)->default(WalletType::Customer->value)->after('id');
        });

        DB::table('wallets')->update(['type' => WalletType::Customer->value]);

        $platformUserId = $this->ensurePlatformUser();
        DB::table('wallets')->insert([
            'user_id' => $platformUserId,
            'type' => WalletType::Platform->value,
            'balance' => 0,
            'currency' => config('billing.currency', 'USD'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensurePlatformUser(): int
    {
        $existing = DB::table('users')->where('email', self::PLATFORM_USER_EMAIL)->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        return (int) DB::table('users')->insertGetId([
            'name' => 'Platform Settlement',
            'email' => self::PLATFORM_USER_EMAIL,
            'username' => 'platform_settlement',
            'password' => bcrypt(str()->random(64)),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('wallets')->where('type', WalletType::Platform->value)->delete();
        DB::table('users')->where('email', self::PLATFORM_USER_EMAIL)->delete();

        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};

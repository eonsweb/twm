<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['sermon-series.manage', 'sermon-topics.manage'])
            ->pluck('id');

        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->insertOrIgnore([
            ['name' => 'sermon-series.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'sermon-topics.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};

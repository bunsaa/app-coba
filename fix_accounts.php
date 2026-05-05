<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Fix email_verified_at for all kepala accounts
$updated = DB::table('users')
    ->where('role', 'kepala_unit')
    ->whereNull('email_verified_at')
    ->update(['email_verified_at' => now()]);
echo "Fixed email_verified_at for $updated kepala accounts" . PHP_EOL;

// Also fix admin
$adminFixed = DB::table('users')
    ->where('email', 'admin@mutu.rsud.go.id')
    ->whereNull('email_verified_at')
    ->update(['email_verified_at' => now()]);
echo "Fixed admin: $adminFixed" . PHP_EOL;

// Also fix ALL other users without verified email
$allFixed = DB::table('users')
    ->whereNull('email_verified_at')
    ->update(['email_verified_at' => now()]);
echo "Fixed all other: $allFixed" . PHP_EOL;

// Show all kepala accounts
echo PHP_EOL . "--- Kepala Unit Accounts ---" . PHP_EOL;
$users = DB::table('users')->where('role', 'kepala_unit')->get();
foreach ($users as $u) {
    echo $u->email . ' | role: ' . $u->role . ' | unit: ' . $u->kode_unit . ' | verified: ' . ($u->email_verified_at ? 'yes' : 'no') . PHP_EOL;
}

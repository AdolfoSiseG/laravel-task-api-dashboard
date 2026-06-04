<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

it('logs in instantly via the demo button', function () {
    User::factory()->create([
        'email' => 'demo@example.com',
        'password' => Hash::make('password'),
    ]);

    Volt::test('auth.login')
        ->call('loginAsDemo')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

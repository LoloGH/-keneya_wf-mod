<?php

namespace Tests;

use App\Models\Doctor;
use App\Models\Receptionist;
use App\Models\Service;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cree les trois roles cloisonnes, comme le fait RoleSeeder au deploiement.
     * A appeler dans les tests ou c'est le code applicatif — et non le test —
     * qui attribue un role.
     */
    protected function seedRoles(): void
    {
        foreach (Roles::all() as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    protected function makeAdmin(): User
    {
        return $this->makeUserWithRole(Roles::ADMIN);
    }

    protected function makeReceptionist(): User
    {
        $user = $this->makeUserWithRole(Roles::RECEPTIONIST);

        Receptionist::create(['user_id' => $user->getKey()]);

        return $user;
    }

    protected function makeDoctor(Service $service, ?string $phone = null): Doctor
    {
        return Doctor::create([
            'user_id' => $this->makeUserWithRole(Roles::DOCTOR)->getKey(),
            'service_id' => $service->getKey(),
            'phone' => $phone,
        ]);
    }

    private function makeUserWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return $user;
    }
}

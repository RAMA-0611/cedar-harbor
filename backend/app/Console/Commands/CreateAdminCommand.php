<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminCommand extends Command
{
    protected $signature = 'rotor:create-admin {email=admin@rotor.test} {password=Admin12345} {--name=Admin}';

    protected $description = 'Crea (o actualiza) un usuario administrador de Proyecto Rotor';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) $this->argument('password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Correo no válido.');

            return self::FAILURE;
        }
        if (strlen($password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            User::query()->create([
                'name' => (string) $this->option('name'),
                'email' => $email,
                'password' => $password,
                'role' => 'ADMIN',
            ]);
            $this->info("Administrador creado: {$email}");
        } else {
            $user->forceFill(['role' => 'ADMIN', 'password' => $password])->save();
            $this->info("Usuario existente actualizado a ADMIN: {$email}");
        }

        return self::SUCCESS;
    }
}

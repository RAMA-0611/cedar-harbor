<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Preparación idempotente del entorno al arrancar el contenedor:
 * migraciones, catálogo de demostración (solo si está vacío) y primer administrador.
 */
class BootstrapCommand extends Command
{
    protected $signature = 'rotor:bootstrap
        {--seed-demo : Carga el catálogo de demostración si no hay productos}
        {--admin-email= : Correo del primer administrador}
        {--admin-password= : Contraseña del primer administrador (si falta, se genera una y se muestra una vez)}
        {--no-migrate : Omite las migraciones}';

    protected $description = 'Migra la base, siembra el catálogo demo (si está vacía) y crea el primer ADMIN si no existe';

    public function handle(): int
    {
        if (! $this->option('no-migrate')) {
            $this->components->info('Aplicando migraciones…');
            Artisan::call('migrate', ['--force' => true]);
            $this->line(trim(Artisan::output()));
        }

        if ($this->option('seed-demo') && Product::query()->count() === 0) {
            $this->components->info('Cargando catálogo de demostración…');
            $this->callSilent('db:seed', ['--class' => CatalogSeeder::class, '--force' => true]);
        }

        if (User::query()->where('role', 'ADMIN')->exists()) {
            $this->components->info('Ya existe un administrador; no se crea otro.');

            return self::SUCCESS;
        }

        // Las credenciales llegan por opción o por entorno (ADMIN_EMAIL / ADMIN_PASSWORD), nunca desde el código.
        $email = (string) ($this->option('admin-email') ?: (getenv('ADMIN_EMAIL') ?: 'admin@rotor.test'));
        $given = (string) ($this->option('admin-password') ?: (getenv('ADMIN_PASSWORD') ?: ''));
        $password = $given !== '' ? $given : Str::password(16, symbols: false);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $this->error('ADMIN_EMAIL inválido o ADMIN_PASSWORD con menos de 8 caracteres.');

            return self::FAILURE;
        }

        User::query()->create(['name' => 'Administrador', 'email' => $email, 'password' => $password, 'role' => 'ADMIN']);
        $this->components->info("Administrador creado: {$email}");
        if ($given === '') {
            $this->components->warn("Contraseña generada (se muestra solo esta vez): {$password}");
        }

        return self::SUCCESS;
    }
}

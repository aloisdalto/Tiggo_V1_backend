<?php

    namespace Database\Seeders;

    use Illuminate\Database\Seeder;
    use App\Models\User;
    use Spatie\Permission\Models\Role;
    use Spatie\Permission\Models\Permission;

    class AdminUserSeeder extends Seeder
    {
        public function run()
        {
            // 1. Asegúrate de que el rol 'admin' exista
            // Si no existe, lo crea.
            if (!Role::where('name', 'admin')->exists()) {
                Role::create(['name' => 'admin']);
            }

            // 2. Busca al usuario que quieres convertir en admin
            // Cambia 'tu_email@ejemplo.com' por el email real de tu usuario
            $user = User::where('email', 'admin@example.com')->first();

            // 3. Asigna el rol si el usuario existe
            if ($user) {
                $user->assignRole('admin');
                $this->command->info("El usuario {$user->email} ahora es Administrador.");
            } else {
                $this->command->error("Usuario no encontrado. Crea el usuario primero.");
            }
        }
    }
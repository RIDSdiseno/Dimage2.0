<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // Roles del sistema (igual que el legacy)
        $roles = [
            'admin',
            'secretaria',
            'radiologo',
            'tecnico',
            'odontologo',
            'contralor',
            'administrativo', // nivel holding
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Permisos básicos por módulo
        $permissions = [
            // Órdenes
            'orden.ver', 'orden.crear', 'orden.editar', 'orden.responder',
            // Pacientes
            'paciente.ver', 'paciente.crear', 'paciente.editar',
            // Usuarios
            'usuario.ver', 'usuario.crear', 'usuario.editar',
            // Clínicas
            'clinica.ver', 'clinica.crear', 'clinica.editar',
            // Holdings
            'holding.ver', 'holding.gestionar',
            // Feriados
            'feriados.gestionar',
            // Contraloría
            'contraloria.ver', 'contraloria.gestionar',
            // Integraciones
            'apikeys.gestionar',
            // Indicadores
            'indicadores.ver',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Asignar permisos por rol
        Role::findByName('admin')->syncPermissions(Permission::all());

        Role::findByName('secretaria')->syncPermissions([
            'orden.ver', 'orden.crear', 'orden.editar',
            'paciente.ver', 'paciente.crear', 'paciente.editar',
        ]);

        Role::findByName('radiologo')->syncPermissions([
            'orden.ver', 'orden.responder',
            'paciente.ver',
        ]);

        Role::findByName('tecnico')->syncPermissions([
            'orden.ver', 'orden.editar',
            'paciente.ver',
        ]);

        Role::findByName('odontologo')->syncPermissions([
            'orden.ver', 'orden.crear',
            'paciente.ver', 'paciente.crear',
        ]);

        Role::findByName('contralor')->syncPermissions([
            'contraloria.ver', 'contraloria.gestionar',
            'orden.ver',
        ]);

        Role::findByName('administrativo')->syncPermissions([
            'orden.ver', 'indicadores.ver',
            'clinica.ver',
        ]);
    }
}

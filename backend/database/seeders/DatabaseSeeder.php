<?php

namespace Database\Seeders;

use App\Enums\CustomerSegment;
use App\Enums\TaskPriority;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Administrador',
            'email' => 'admin@rauzee.test',
            'password' => 'password',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $alvorada = $this->customer('Imobiliária Alvorada', '11222333000181', 'contato@alvorada.test', CustomerSegment::RealEstate, 'Marina Alves');
        $prata = $this->customer('Corban Prata', '11444777000161', 'financeiro@prata.test', CustomerSegment::Corban, 'Rafael Prata');
        $horizonte = $this->customer('Horizonte Serviços', '52998224725', 'contato@horizonte.test', CustomerSegment::Other, 'Carla Nunes');

        Task::factory()->for($alvorada)->create([
            'created_by' => $admin->id,
            'title' => 'Enviar documentação inicial',
            'description' => 'Reunir contrato social e comprovante de endereço.',
            'due_date' => today()->subDays(3),
            'priority' => TaskPriority::High,
        ]);

        Task::factory()->for($alvorada)->create([
            'created_by' => $admin->id,
            'title' => 'Agendar reunião de alinhamento',
            'description' => 'Confirmar a agenda com a Marina e reservar a sala.',
            'due_date' => today()->addDays(4),
            'priority' => TaskPriority::Medium,
        ]);

        Task::factory()->for($prata)->completed()->create([
            'created_by' => $admin->id,
            'title' => 'Validar dados bancários',
            'description' => 'Conferir agência e conta antes do primeiro repasse.',
            'due_date' => today()->subDays(6),
            'priority' => TaskPriority::High,
        ]);

        Task::factory()->for($horizonte)->create([
            'created_by' => $admin->id,
            'title' => 'Revisar tabela de comissões',
            'description' => 'Aplicar os percentuais novos combinados para o trimestre.',
            'due_date' => today(),
            'priority' => TaskPriority::Low,
        ]);
    }

    private function customer(string $name, string $document, string $email, CustomerSegment $segment, string $contact): Customer
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => UserRole::Customer,
        ]);

        return Customer::factory()->for($user)->create([
            'name' => $name,
            'document' => $document,
            'email' => $email,
            'segment' => $segment,
            'contact_name' => $contact,
        ]);
    }
}

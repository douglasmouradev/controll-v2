<?php
declare(strict_types=1);

function ui_status_badge_class(string $status): string
{
	return match ($status) {
		'Fechado' => 'badge badge-green',
		'Em andamento', 'Em Andamento' => 'badge badge-yellow',
		'Agendado' => 'badge badge-purple',
		'Aberto' => 'badge badge-blue',
		default => 'badge badge-gray',
	};
}

function ui_priority_badge_class(string $priority): string
{
	return match ($priority) {
		'Alta' => 'badge badge-red',
		'Média' => 'badge badge-yellow',
		'Baixa' => 'badge badge-gray',
		default => 'badge badge-gray',
	};
}

function ui_role_badge_class(string $role): string
{
	$role = strtolower(trim($role));
	return match ($role) {
		'admin' => 'badge badge-purple',
		'support', 'suporte' => 'badge badge-blue',
		default => 'badge badge-gray',
	};
}

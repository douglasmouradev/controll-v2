<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\TicketController;
use App\Controllers\UserController;
use App\Controllers\ReportController;

return [
	'GET' => [
		'/' => [DashboardController::class, 'index'],
		'/login' => [AuthController::class, 'loginForm'],
		'/logout' => [AuthController::class, 'logout'],
		'/change-password-first' => [AuthController::class, 'changePasswordFirst'],
		'/dashboard/dailies' => [DashboardController::class, 'dailyStats'],
		'/dashboard/status-stats' => [DashboardController::class, 'statusStats'],
		'/dashboard/enderecos' => [DashboardController::class, 'storeAddresses'],
		'/tickets' => [TicketController::class, 'index'],
		'/tickets/view' => [TicketController::class, 'show'],
		'/tickets/attachments' => [TicketController::class, 'attachments'],
		'/users' => [UserController::class, 'index'],
		'/users/credit-history' => [UserController::class, 'creditHistory'],
		'/reports/pdf' => [ReportController::class, 'pdf'],
		'/reports/xlsx' => [ReportController::class, 'xlsx'],
		'/reports/csv' => [ReportController::class, 'csv'],
	],
	'POST' => [
		'/login' => [AuthController::class, 'login'],
		'/change-password-first-update' => [AuthController::class, 'updatePasswordFirst'],
		'/tickets/create' => [TicketController::class, 'create'],
		'/tickets/status' => [TicketController::class, 'updateStatus'],
		'/tickets/assign' => [TicketController::class, 'assignToMe'],
		'/tickets/response' => [TicketController::class, 'saveResponse'],
		'/users/create' => [UserController::class, 'create'],
		'/users/update' => [UserController::class, 'update'],
		'/users/delete' => [UserController::class, 'delete'],
		'/users/credits' => [UserController::class, 'adjustCredits'],
	],
];

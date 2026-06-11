<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\TicketController;
use App\Controllers\UserController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\HealthController;

return [
	'GET' => [
		'/' => [DashboardController::class, 'index'],
		'/login' => [AuthController::class, 'loginForm'],
		'/auditoria' => [AuthController::class, 'auditoria'],
		'/logout' => [AuthController::class, 'logout'],
		'/change-password-first' => [AuthController::class, 'changePasswordFirst'],
		'/health' => [HealthController::class, 'index'],
		'/dashboard/dailies' => [DashboardController::class, 'dailyStats'],
		'/dashboard/summary' => [DashboardController::class, 'summaryStats'],
		'/dashboard/daily-destinations' => [DashboardController::class, 'dailyDestinationStats'],
		'/dashboard/status-stats' => [DashboardController::class, 'statusStats'],
		'/dashboard/credit-usage' => [DashboardController::class, 'creditUsageStats'],
		'/dashboard/inventory-stats' => [DashboardController::class, 'inventoryStats'],
		'/dashboard/inventory-download' => [DashboardController::class, 'downloadInventoryFile'],
		'/dashboard/purchased-dailies' => [DashboardController::class, 'purchasedDailiesStats'],
		'/dashboard/purchased-dailies-download' => [DashboardController::class, 'downloadPurchasedDailiesFile'],
		'/dashboard/enderecos' => [DashboardController::class, 'storeAddresses'],
		'/tickets' => [TicketController::class, 'index'],
		'/tickets/view' => [TicketController::class, 'show'],
		'/tickets/attachments' => [TicketController::class, 'attachments'],
		'/tickets/attachment-download' => [TicketController::class, 'downloadAttachment'],
		'/tickets/clone' => [TicketController::class, 'cloneTicket'],
		'/users' => [UserController::class, 'index'],
		'/users/credit-history' => [UserController::class, 'creditHistory'],
		'/reports/pdf' => [ReportController::class, 'pdf'],
		'/reports/xlsx' => [ReportController::class, 'xlsx'],
		'/reports/csv' => [ReportController::class, 'csv'],
		'/settings/maintenance' => [SettingsController::class, 'maintenanceStatus'],
		'/settings' => [SettingsController::class, 'index'],
	],
	'POST' => [
		'/login' => [AuthController::class, 'login'],
		'/change-password-first-update' => [AuthController::class, 'updatePasswordFirst'],
		'/tickets/create' => [TicketController::class, 'create'],
		'/tickets/update' => [TicketController::class, 'update'],
		'/tickets/status' => [TicketController::class, 'updateStatus'],
		'/tickets/assign' => [TicketController::class, 'assignToMe'],
		'/tickets/response' => [TicketController::class, 'saveResponse'],
		'/tickets/update-technician' => [TicketController::class, 'updateTechnician'],
		'/tickets/delete' => [TicketController::class, 'delete'],
		'/tickets/attachment-delete' => [TicketController::class, 'deleteAttachment'],
		'/tickets/clone' => [TicketController::class, 'cloneTicket'],
		'/users/create' => [UserController::class, 'create'],
		'/users/update' => [UserController::class, 'update'],
		'/users/delete' => [UserController::class, 'delete'],
		'/users/credits' => [UserController::class, 'adjustCredits'],
		'/users/credits/reset' => [UserController::class, 'resetCredits'],
		'/users/credit-history/clear' => [UserController::class, 'clearCreditHistory'],
		'/dashboard/inventory-upload' => [DashboardController::class, 'uploadInventoryFile'],
		'/dashboard/purchased-dailies-upload' => [DashboardController::class, 'uploadPurchasedDailiesFile'],
		'/settings/maintenance' => [SettingsController::class, 'maintenanceToggle'],
		'/settings/update' => [SettingsController::class, 'update'],
	],
];

document.addEventListener('click', (e) => {
	if (e.target.id === 'btn-global-credits-ticket' || e.target.id === 'btn-global-credits-ticket-users') {
		openGlobalCreditsModal('ticket');
	}
	if (e.target.id === 'btn-global-credits-daily' || e.target.id === 'btn-global-credits-daily-users') {
		openGlobalCreditsModal('daily');
	}
	if (e.target.id === 'btn-global-credits-project' || e.target.id === 'btn-global-credits-project-users') {
		openGlobalCreditsModal('project_dailies');
	}
	if (e.target.id === 'btn-reset-credits-ticket') {
		resetGlobalCredits('ticket');
	}
	if (e.target.id === 'btn-reset-credits-daily') {
		resetGlobalCredits('daily');
	}
	if (e.target.id === 'btn-reset-credits-project') {
		resetGlobalCredits('project_dailies');
	}
});

function openGlobalCreditsModal(type) {
	const modalCredits = document.getElementById('modal-credits');
	if (!modalCredits) return;

	const creditsUserNameEl = document.getElementById('credits-modal-user-name');
	const creditsCurrentEl = document.getElementById('credits-current');
	const creditsDeltaInput = document.getElementById('credits-delta');
	const creditsUserIdInput = document.getElementById('credits-user-id');
	const creditsTypeInput = document.getElementById('credits-type');
	const creditsTypeLabelEl = document.getElementById('credits-type-label');
	const creditsPreviewEl = document.getElementById('credits-preview');

	if (!creditsUserNameEl || !creditsCurrentEl || !creditsDeltaInput || !creditsUserIdInput) {
		return;
	}

	creditsUserIdInput.value = '0';
	creditsDeltaInput.value = '0';
	creditsTypeInput.value = type;
	creditsUserNameEl.textContent = 'Todos os Usuários (Global)';

	let currentBalance = 0;
	const table = document.querySelector('#users-tbody');
	if (table) {
		const rows = table.querySelectorAll('tr');
		for (const row of rows) {
			const perfil = row.querySelector('td:nth-child(4)')?.textContent?.trim();
			if (perfil === 'user') {
				let cellIndex = 5;
				if (type === 'daily') {
					cellIndex = 6;
				} else if (type === 'project_dailies') {
					cellIndex = 7;
				}
				const cell = row.querySelector(`td:nth-child(${cellIndex})`);
				if (cell) {
					currentBalance = parseInt(cell.textContent?.trim() || '0', 10);
				}
				break;
			}
		}
	}

	window.creditsCurrentValue = currentBalance;
	window.currentCreditsType = type;
	creditsCurrentEl.textContent = currentBalance;
	creditsPreviewEl.textContent = currentBalance;

	if (creditsTypeLabelEl) {
		if (type === 'daily') {
			creditsTypeLabelEl.textContent = 'Ajustando Créditos de Diária para TODOS os usuários';
		} else if (type === 'project_dailies') {
			creditsTypeLabelEl.textContent = 'Ajustando Créditos de Diárias Projeto para TODOS os usuários';
		} else {
			creditsTypeLabelEl.textContent = 'Ajustando Créditos de Ticket para TODOS os usuários';
		}
	}

	modalCredits.showModal();
}

async function resetGlobalCredits(type) {
	if (!confirm('Tem certeza que deseja ZERAR os créditos e o histórico deste tipo para TODOS os usuários? Esta ação não pode ser desfeita.')) {
		return;
	}
	const fd = new FormData();
	fd.set('type', type);
	try {
		const res = await fetch('/users/credits/reset', {
			method: 'POST',
			body: fd,
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		});
		const data = await res.json();
		if (data.success) {
			if (typeof showToast === 'function') {
				showToast(data.message || 'Créditos resetados com sucesso');
			}
			try {
				const res2 = await fetch('/dashboard/credit-usage');
				const data2 = await res2.json();
				if (data2.success && data2.summary) {
					const ticket = data2.summary.ticket || { purchased: 0, spent: 0, available: 0 };
					const daily = data2.summary.daily || { purchased: 0, spent: 0, available: 0 };
					const project = data2.summary.project_dailies || { purchased: 0, spent: 0, available: 0 };

					const ticketEl = document.getElementById('ticket-summary');
					if (ticketEl) {
						ticketEl.textContent = `Comprados ${ticket.purchased} / Consumidos ${ticket.spent} / Disponível ${ticket.available}`;
					}
					const ticketAvailEl = document.getElementById('ticket-available');
					if (ticketAvailEl) ticketAvailEl.textContent = ticket.available;

					const dailyEl = document.getElementById('daily-summary');
					if (dailyEl) {
						dailyEl.textContent = `Comprados ${daily.purchased} / Consumidos ${daily.spent} / Disponível ${daily.available}`;
					}
					const dailyAvailEl = document.getElementById('daily-available');
					if (dailyAvailEl) dailyAvailEl.textContent = daily.available;
					const dailyUsedEl = document.getElementById('daily-used');
					if (dailyUsedEl) {
						const totalUsedDailies = Number(data2.summary.total_used_dailies)
							|| ((Number(daily.spent) || 0) + (Number(project.spent) || 0));
						dailyUsedEl.textContent = totalUsedDailies;
					}

					const projectEl = document.getElementById('project-dailies-summary');
					if (projectEl) {
						projectEl.textContent = `Comprados ${project.purchased} / Consumidos ${project.spent} / Disponível ${project.available}`;
					}
					const projectAvailEl = document.getElementById('project-dailies-available');
					if (projectAvailEl) projectAvailEl.textContent = project.available;

					if (typeof renderCreditsPie === 'function') {
						creditsTicketPie = renderCreditsPie('credits-ticket-pie', ticket, creditsTicketPie);
						creditsDailyPie = renderCreditsPie('credits-daily-pie', daily, creditsDailyPie);
						creditsProjectPie = renderCreditsPie('credits-project-pie', project, creditsProjectPie);
					}
				}
			} catch (err) {
				console.error('Erro ao recarregar dados de créditos após reset:', err);
			}
		} else if (typeof showToast === 'function') {
			showToast(data.message || 'Erro ao resetar créditos');
		}
	} catch (error) {
		console.error('Erro ao resetar créditos:', error);
		if (typeof showToast === 'function') {
			showToast('Erro ao conectar com o servidor');
		}
	}
}

document.addEventListener('DOMContentLoaded', function () {
	if (
		document.getElementById('credits-ticket-pie') ||
		document.getElementById('credits-daily-pie') ||
		document.getElementById('credits-project-pie')
	) {
		if (typeof loadCreditPieCharts === 'function') {
			loadCreditPieCharts();
		}
	}
});

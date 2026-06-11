<?php
/** @var array $user */
require_once BASE_PATH . '/app/Views/helpers/auth.php';
?>
<!-- Modal de Abrir Chamado -->
<dialog id="modal-abrir-chamado" class="rounded-lg w-11/12 max-w-3xl p-0">
	<div class="bg-blue-700 text-white px-6 py-4 rounded-t-lg">
		<h2 class="text-lg font-semibold">Abrir Novo Chamado</h2>
	</div>
	<div class="p-6">
		<form id="new-ticket-form" class="grid grid-cols-2 gap-4">
			<input class="col-span-2 border rounded px-3 py-2" name="title" placeholder="Título" required>
			<select class="border rounded px-3 py-2" name="priority" required>
				<option value="">Prioridade</option>
				<option>Baixa</option><option>Média</option><option>Alta</option>
			</select>
			<input class="border rounded px-3 py-2" name="category" placeholder="Categoria" required>
			<input class="border rounded px-3 py-2" name="name" placeholder="Nome" required>
			<input class="border rounded px-3 py-2" name="registration" placeholder="Matrícula">
			<input class="col-span-2 border rounded px-3 py-2" name="unit" placeholder="Unidade" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase();" required>
			<div class="col-span-2 flex gap-2">
				<input class="border rounded px-3 py-2 flex-1" name="cep" id="cep" placeholder="CEP" required>
				<button type="button" id="buscar-cep" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">Buscar CEP</button>
			</div>
			<input class="col-span-2 border rounded px-3 py-2" name="address" id="address" placeholder="Endereço" required>
			<input class="border rounded px-3 py-2" name="city" id="city" placeholder="Cidade" required>
			<input class="border rounded px-3 py-2" name="uf" id="uf" placeholder="UF" required>
			<textarea class="col-span-2 border rounded px-3 py-2" name="description" placeholder="Descrição" rows="4" required></textarea>
			<input class="border rounded px-3 py-2" name="internal_order" placeholder="Pedido (interno)">
			<input class="border rounded px-3 py-2" name="invoice" placeholder="NF">
			<input class="border rounded px-3 py-2" name="daily_rates" placeholder="Diárias">
			<input class="border rounded px-3 py-2" name="external_ticket" placeholder="Tickets">
			<div class="col-span-2 flex gap-2 justify-end">
				<button type="button" id="cancelar-chamado" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancelar</button>
				<button type="submit" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">Abrir Chamado</button>
			</div>
		</form>
	</div>
</dialog>

<!-- Modal de Criar/Editar Usuário -->
<dialog id="modal-usuario" class="rounded-lg w-11/12 max-w-2xl p-0">
	<div class="bg-blue-700 text-white px-6 py-4 rounded-t-lg">
		<h2 class="text-lg font-semibold" id="modal-usuario-title">Criar Usuário</h2>
	</div>
	<div class="p-6">
		<form id="user-form" class="space-y-4">
			<input type="hidden" id="user-id" name="id">
			<div>
				<label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
				<input type="text" id="user-name" name="name" class="w-full border rounded px-3 py-2" required>
			</div>
			<div>
				<label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
				<input type="email" id="user-email" name="email" class="w-full border rounded px-3 py-2" required>
			</div>
			<div>
				<label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
				<input type="password" id="user-password" name="password" class="w-full border rounded px-3 py-2">
				<p class="text-xs text-gray-500 mt-1 hidden" id="password-hint">Deixe em branco para manter a senha atual ao editar</p>
			</div>
			<div>
				<label class="block text-sm font-medium text-gray-700 mb-1">Perfil</label>
				<select id="user-role" name="role" class="w-full border rounded px-3 py-2" required>
					<option value="usuario">Usuário</option>
					<option value="suporte">Suporte</option>
					<option value="admin">Admin</option>
					<option value="superadmin">Super Admin</option>
				</select>
			</div>
			<div class="flex gap-2 justify-end">
				<button type="button" id="cancelar-usuario" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancelar</button>
				<button type="submit" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">Salvar</button>
			</div>
		</form>
	</div>
</dialog>

<!-- Modal de Detalhes do Chamado -->
<dialog id="ticket-modal" class="rounded-lg w-11/12 max-w-2xl p-0">
	<div class="bg-blue-700 text-white px-4 py-3 rounded-t-lg">Detalhes do Chamado</div>
	<div class="p-4 space-y-2" id="ticket-modal-body"></div>
	<?php if (view_is_support_or_admin($user)): ?>
		<div class="p-4 border-t space-y-4">
			<div class="grid grid-cols-3 gap-3 text-sm" id="technician-fields-container">
				<div>
					<label class="block text-xs text-gray-600 mb-1">Nome do técnico</label>
					<input type="text" id="technician-name-detail" class="w-full border rounded px-2 py-1" placeholder="Nome do técnico">
				</div>
				<div>
					<label class="block text-xs text-gray-600 mb-1">RG do técnico</label>
					<input type="text" id="technician-rg-detail" class="w-full border rounded px-2 py-1" placeholder="RG do técnico">
				</div>
				<div>
					<label class="block text-xs text-gray-600 mb-1">CPF do técnico</label>
					<input type="text" id="technician-cpf-detail" class="w-full border rounded px-2 py-1" placeholder="CPF do técnico">
				</div>
			</div>
			<button id="btn-save-technician" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-900 text-sm">Salvar dados do técnico</button>
			<div class="border-t pt-4">
			<label class="block text-sm font-medium text-gray-700 mb-2">Resposta do Suporte</label>
			<textarea id="support-response" rows="4" class="w-full border rounded px-3 py-2" placeholder="Digite sua resposta para o usuário..."></textarea>
			
			<div class="mt-3">
				<label class="block text-sm font-medium text-gray-700 mb-2">Anexar Imagens</label>
				<input type="file" id="support-images" accept="image/*" multiple class="w-full border rounded px-3 py-2">
				<div id="image-preview" class="mt-2 grid grid-cols-3 gap-2"></div>
			</div>
			
			<button id="btn-save-response" class="mt-2 bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">Salvar Resposta</button>
		</div>
	<?php endif; ?>
	<div class="p-4 pt-0 flex gap-2 justify-end">
		<?php if (view_is_support_or_admin($user)): ?>
			<button data-status="Aberto" class="status-btn bg-gray-100 px-3 py-1 rounded hover:bg-gray-200">Aberto</button>
			<button data-status="Em andamento" class="status-btn bg-yellow-100 px-3 py-1 rounded hover:bg-yellow-200">Em andamento</button>
			<button data-status="Fechado" class="status-btn bg-green-100 px-3 py-1 rounded hover:bg-green-200">Fechado</button>
		<?php endif; ?>
		<button id="modal-close" class="bg-blue-700 text-white px-4 py-1.5 rounded hover:bg-blue-800">Fechar</button>
	</div>
</dialog>

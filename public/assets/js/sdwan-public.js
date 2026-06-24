document.addEventListener('DOMContentLoaded', function () {
	const form = document.getElementById('sdwan-public-form');
	if (!form) return;

	const code = String(window.SDWAN_PUBLIC_CODE || form.querySelector('input[name="code"]')?.value || '').trim();
	const lojaInput = document.getElementById('sdwan-public-loja');
	const lojaDatalist = document.getElementById('sdwan-public-loja-list');
	const lojaHint = document.getElementById('sdwan-public-loja-hint');
	const imageInput = document.getElementById('sdwan-public-image');
	const imagePreview = document.getElementById('sdwan-public-image-preview');
	const imagePreviewImg = document.getElementById('sdwan-public-image-preview-img');
	const submitBtn = document.getElementById('sdwan-public-submit');
	const expiresEl = document.getElementById('sdwan-public-expires-at');
	let storeSiglas = [];
	let previewObjectUrl = null;

	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function formatDateTime(value) {
		if (!value) return '';
		const normalized = String(value).includes('T') ? value : String(value).replace(' ', 'T');
		const date = new Date(normalized);
		if (Number.isNaN(date.getTime())) return value;
		return date.toLocaleString('pt-BR');
	}

	if (expiresEl) {
		expiresEl.textContent = formatDateTime(expiresEl.textContent);
	}

	async function loadStoreSiglas() {
		try {
			const res = await fetch('/sdwan/enderecos?code=' + encodeURIComponent(code), {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			const list = data.success && Array.isArray(data.data) ? data.data : [];
			storeSiglas = list
				.map((item) => ({
					sigla: String(item.sigla || '').trim().toUpperCase(),
					endereco: String(item.endereco || item.ENDERECO || '').trim(),
				}))
				.filter((item) => item.sigla !== '')
				.sort((a, b) => a.sigla.localeCompare(b.sigla, 'pt-BR'));
		} catch (error) {
			console.error('Erro ao carregar siglas:', error);
			storeSiglas = [];
		}
	}

	function populateLojaDatalist() {
		if (!lojaDatalist) return;
		lojaDatalist.innerHTML = storeSiglas
			.map((item) => `<option value="${escapeHtml(item.sigla)}"></option>`)
			.join('');
	}

	function findStoreBySigla(query) {
		const sigla = String(query || '').trim().toUpperCase();
		return storeSiglas.find((item) => item.sigla === sigla) || null;
	}

	function findStoresByPrefix(query) {
		const sigla = String(query || '').trim().toUpperCase();
		return storeSiglas.filter((item) => item.sigla.startsWith(sigla));
	}

	function updateLojaHint(store) {
		if (!lojaHint) return;
		lojaHint.textContent = store
			? (store.endereco || `Sigla ${store.sigla} encontrada na planilha de lojas.`)
			: 'Digite a sigla para buscar na planilha de lojas.';
	}

	function completeLojaSigla() {
		if (!lojaInput) return;
		const query = lojaInput.value.trim().toUpperCase();
		lojaInput.value = query;
		if (!query) {
			updateLojaHint(null);
			return;
		}
		const exact = findStoreBySigla(query);
		if (exact) {
			lojaInput.value = exact.sigla;
			updateLojaHint(exact);
			return;
		}
		const matches = findStoresByPrefix(query);
		if (matches.length === 1) {
			lojaInput.value = matches[0].sigla;
			updateLojaHint(matches[0]);
			return;
		}
		updateLojaHint(null);
	}

	function clearImagePreview() {
		if (previewObjectUrl) {
			URL.revokeObjectURL(previewObjectUrl);
			previewObjectUrl = null;
		}
		if (imagePreview) imagePreview.classList.add('hidden');
		if (imagePreviewImg) imagePreviewImg.removeAttribute('src');
	}

	lojaInput?.addEventListener('input', () => {
		lojaInput.value = lojaInput.value.toUpperCase();
		updateLojaHint(findStoreBySigla(lojaInput.value));
	});
	lojaInput?.addEventListener('blur', completeLojaSigla);
	lojaInput?.addEventListener('change', completeLojaSigla);

	imageInput?.addEventListener('change', () => {
		clearImagePreview();
		const file = imageInput.files && imageInput.files[0];
		if (!file) return;
		if (!file.type.startsWith('image/')) {
			if (typeof showToast === 'function') showToast('Selecione um arquivo de imagem válido');
			imageInput.value = '';
			return;
		}
		previewObjectUrl = URL.createObjectURL(file);
		if (imagePreviewImg) imagePreviewImg.src = previewObjectUrl;
		if (imagePreview) imagePreview.classList.remove('hidden');
	});

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		completeLojaSigla();

		submitBtn.disabled = true;
		const originalText = submitBtn.textContent;
		submitBtn.textContent = 'Enviando...';

		try {
			const res = await fetch('/sdwan/cadastro', {
				method: 'POST',
				body: new FormData(form),
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (data.success) {
				if (typeof showToast === 'function') showToast(data.message || 'Registro enviado com sucesso', 'success');
				form.reset();
				form.querySelector('input[name="code"]').value = code;
				clearImagePreview();
				updateLojaHint(null);
			} else if (typeof showToast === 'function') {
				showToast(data.message || 'Erro ao enviar registro');
			}
		} catch (error) {
			console.error('Erro ao enviar SDWAN público:', error);
			if (typeof showToast === 'function') showToast('Erro ao conectar com o servidor');
		} finally {
			submitBtn.disabled = false;
			submitBtn.textContent = originalText;
		}
	});

	loadStoreSiglas().then(populateLojaDatalist);
});

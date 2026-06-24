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
	const imageSizeHint = document.getElementById('sdwan-public-image-size-hint');
	const submitBtn = document.getElementById('sdwan-public-submit');
	const expiresEl = document.getElementById('sdwan-public-expires-at');
	const addressEl = document.getElementById('sdwan-public-loja-address');
	const successBox = document.getElementById('sdwan-public-success');
	const successText = document.getElementById('sdwan-public-success-text');
	const newEntryBtn = document.getElementById('sdwan-public-new-entry');
	let storeSiglas = [];
	let previewObjectUrl = null;
	let compressedImageFile = null;

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
		if (!store) {
			lojaHint.textContent = 'Digite a sigla para buscar na planilha de lojas.';
			if (addressEl) {
				addressEl.textContent = '';
				addressEl.classList.add('hidden');
			}
			return;
		}
		lojaHint.textContent = `Sigla ${store.sigla} encontrada.`;
		if (addressEl && store.endereco) {
			addressEl.textContent = store.endereco;
			addressEl.classList.remove('hidden');
		}
	}

	function showSuccessPanel(entry, message) {
		if (!successBox || !successText) return;
		const loja = entry?.loja || lojaInput?.value || '';
		const pdv = entry?.pdv_numero || document.getElementById('sdwan-public-pdv-numero')?.value || '-';
		successText.textContent = message || `Loja ${loja} — PDV ${pdv} registrado com sucesso.`;
		successBox.classList.remove('hidden');
		form.classList.add('hidden');
	}

	function hideSuccessPanel() {
		if (successBox) successBox.classList.add('hidden');
		if (form) form.classList.remove('hidden');
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
		compressedImageFile = null;
		if (imagePreview) imagePreview.classList.add('hidden');
		if (imagePreviewImg) imagePreviewImg.removeAttribute('src');
		if (imageSizeHint) {
			imageSizeHint.textContent = 'A imagem será otimizada automaticamente antes do envio.';
		}
	}

	function updateImageSizeHint(result) {
		if (!imageSizeHint || !result) return;
		const formatSize = typeof formatFileSize === 'function' ? formatFileSize : (bytes) => String(bytes);
		if (result.optimized && result.compressedSize < result.originalSize) {
			imageSizeHint.textContent = `Imagem otimizada: ${formatSize(result.originalSize)} → ${formatSize(result.compressedSize)}`;
			return;
		}
		imageSizeHint.textContent = `Tamanho da imagem: ${formatSize(result.compressedSize || result.originalSize || 0)}`;
	}

	async function prepareSelectedImage(file) {
		if (imageSizeHint) imageSizeHint.textContent = 'Otimizando imagem...';
		if (typeof compressImageFile !== 'function') {
			compressedImageFile = file;
			updateImageSizeHint({ originalSize: file.size, compressedSize: file.size, optimized: false });
			return file;
		}
		const result = await compressImageFile(file);
		compressedImageFile = result.file;
		updateImageSizeHint(result);
		return result.file;
	}

	lojaInput?.addEventListener('input', () => {
		lojaInput.value = lojaInput.value.toUpperCase();
		updateLojaHint(findStoreBySigla(lojaInput.value));
	});
	lojaInput?.addEventListener('blur', completeLojaSigla);
	lojaInput?.addEventListener('change', completeLojaSigla);

	imageInput?.addEventListener('change', async () => {
		clearImagePreview();
		const file = imageInput.files && imageInput.files[0];
		if (!file) return;
		if (!file.type.startsWith('image/')) {
			if (typeof showToast === 'function') showToast('Selecione um arquivo de imagem válido');
			imageInput.value = '';
			return;
		}

		try {
			const prepared = await prepareSelectedImage(file);
			previewObjectUrl = URL.createObjectURL(prepared);
			if (imagePreviewImg) imagePreviewImg.src = previewObjectUrl;
			if (imagePreview) imagePreview.classList.remove('hidden');
		} catch (error) {
			console.error('Erro ao otimizar imagem:', error);
			if (typeof showToast === 'function') showToast('Não foi possível otimizar a imagem. Tente outra foto.');
			imageInput.value = '';
			clearImagePreview();
		}
	});

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		completeLojaSigla();

		submitBtn.disabled = true;
		const originalText = submitBtn.textContent;
		submitBtn.textContent = 'Enviando...';

		try {
			const formData = new FormData(form);
			if (compressedImageFile) {
				formData.set('image', compressedImageFile, compressedImageFile.name);
			} else if (imageInput?.files?.[0] && typeof compressImageFile === 'function') {
				submitBtn.textContent = 'Otimizando imagem...';
				const result = await compressImageFile(imageInput.files[0]);
				compressedImageFile = result.file;
				formData.set('image', result.file, result.file.name);
			}

			submitBtn.textContent = 'Enviando...';
			const res = await fetch('/sdwan/cadastro', {
				method: 'POST',
				body: formData,
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (data.success) {
				if (data.warning && typeof showToast === 'function') showToast(data.warning, 'info');
				showSuccessPanel(data.entry || {}, data.message || 'Registro enviado com sucesso');
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

	newEntryBtn?.addEventListener('click', () => {
		form.reset();
		form.querySelector('input[name="code"]').value = code;
		clearImagePreview();
		updateLojaHint(null);
		hideSuccessPanel();
	});

	loadStoreSiglas().then(populateLojaDatalist);
});

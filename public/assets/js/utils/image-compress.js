(function (global) {
	'use strict';

	function formatFileSize(bytes) {
		const size = Number(bytes) || 0;
		if (size < 1024) return size + ' B';
		if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
		return (size / (1024 * 1024)).toFixed(1) + ' MB';
	}

	/**
	 * @param {File} file
	 * @param {{ maxWidth?: number, maxHeight?: number, quality?: number }} [options]
	 * @returns {Promise<{ file: File, originalSize: number, compressedSize: number, optimized: boolean }>}
	 */
	function compressImageFile(file, options) {
		const settings = Object.assign({
			maxWidth: 1920,
			maxHeight: 1920,
			quality: 0.82,
		}, options || {});

		if (!file || !String(file.type || '').startsWith('image/')) {
			return Promise.resolve({
				file: file,
				originalSize: file ? file.size : 0,
				compressedSize: file ? file.size : 0,
				optimized: false,
			});
		}

		if (file.type === 'image/gif') {
			return Promise.resolve({
				file: file,
				originalSize: file.size,
				compressedSize: file.size,
				optimized: false,
			});
		}

		return new Promise((resolve) => {
			const url = URL.createObjectURL(file);
			const img = new Image();

			img.onload = function () {
				URL.revokeObjectURL(url);

				let width = img.naturalWidth || img.width;
				let height = img.naturalHeight || img.height;
				if (!width || !height) {
					resolve({
						file: file,
						originalSize: file.size,
						compressedSize: file.size,
						optimized: false,
					});
					return;
				}

				const ratio = Math.min(
					1,
					settings.maxWidth / width,
					settings.maxHeight / height
				);
				width = Math.max(1, Math.round(width * ratio));
				height = Math.max(1, Math.round(height * ratio));

				const canvas = document.createElement('canvas');
				canvas.width = width;
				canvas.height = height;
				const ctx = canvas.getContext('2d');
				if (!ctx) {
					resolve({
						file: file,
						originalSize: file.size,
						compressedSize: file.size,
						optimized: false,
					});
					return;
				}

				ctx.drawImage(img, 0, 0, width, height);
				canvas.toBlob(function (blob) {
					if (!blob) {
						resolve({
							file: file,
							originalSize: file.size,
							compressedSize: file.size,
							optimized: false,
						});
						return;
					}

					const baseName = String(file.name || 'imagem').replace(/\.[^.]+$/, '') || 'imagem';
					const compressed = new File([blob], baseName + '.jpg', {
						type: 'image/jpeg',
						lastModified: Date.now(),
					});

					resolve({
						file: compressed,
						originalSize: file.size,
						compressedSize: compressed.size,
						optimized: compressed.size < file.size || ratio < 1,
					});
				}, 'image/jpeg', settings.quality);
			};

			img.onerror = function () {
				URL.revokeObjectURL(url);
				resolve({
					file: file,
					originalSize: file.size,
					compressedSize: file.size,
					optimized: false,
				});
			};

			img.src = url;
		});
	}

	global.formatFileSize = formatFileSize;
	global.compressImageFile = compressImageFile;
})(window);

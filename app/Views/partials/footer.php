	</main>
	<script>
		function showToast(msg) {
			const el = document.getElementById('toast');
			el.textContent = msg;
			el.classList.remove('hidden');
			setTimeout(() => el.classList.add('hidden'), 2500);
		}
	</script>
	<footer class="mt-10 py-6 text-center text-sm text-gray-500">
		&copy; 2025 Controll IT • Plataforma proprietária de Help Desk
	</footer>
</body>
</html>



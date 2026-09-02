<script>
	(function () {
		function initialisePasswordToggles() {
			document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
				if (button.dataset.passwordToggleReady === 'true') {
					return;
				}

				const targetId = button.getAttribute('data-password-toggle-target');
				const target = targetId ? document.getElementById(targetId) : null;
				if (!target) {
					return;
				}

				const showIcon = button.querySelector('[data-password-icon="show"]');
				const hideIcon = button.querySelector('[data-password-icon="hide"]');

				button.addEventListener('click', function () {
					const shouldShow = target.type === 'password';
					target.type = shouldShow ? 'text' : 'password';
					button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
					button.setAttribute('title', shouldShow ? 'Hide password' : 'Show password');
					button.setAttribute('aria-pressed', String(shouldShow));

					if (showIcon) {
						showIcon.hidden = shouldShow;
					}
					if (hideIcon) {
						hideIcon.hidden = !shouldShow;
					}
				});

				button.dataset.passwordToggleReady = 'true';
			});
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initialisePasswordToggles);
		} else {
			initialisePasswordToggles();
		}
	})();
</script>

/**
 * Infomaniak AI — Admin Settings Scripts
 *
 * @since 1.1.0
 */

/* ==========================================================================
   Test Connection
   ========================================================================== */
(function () {
	'use strict';

	var btn = document.getElementById('ik-test-connection');
	if (!btn) return;

	var feedback = document.getElementById('ik-test-feedback');

	btn.addEventListener('click', function () {
		btn.disabled = true;
		btn.textContent = btn.dataset.loading;
		feedback.className = 'ik-feedback';
		feedback.textContent = '';

		fetch(window.ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=infomaniak_ai_test_connection&_ajax_nonce=' + encodeURIComponent(btn.dataset.nonce),
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				feedback.textContent = data.data;
				feedback.className = 'ik-feedback ' + (data.success ? 'ik-feedback--success' : 'ik-feedback--error');
			})
			.catch(function () {
				feedback.textContent = 'Request failed.';
				feedback.className = 'ik-feedback ik-feedback--error';
			})
			.finally(function () {
				btn.disabled = false;
				btn.textContent = btn.dataset.label;
			});
	});
})();

/* ==========================================================================
   Commands — Delete Handler
   ========================================================================== */
(function () {
	'use strict';

	var list = document.getElementById('ik-commands-list');
	if (!list || typeof infomaniakAiAdmin === 'undefined') return;

	list.addEventListener('click', function (e) {
		var btn = e.target.closest('.ik-cmd-delete');
		if (!btn) return;

		var slug = btn.dataset.slug;
		if (!slug) return;

		if (!confirm('Delete command "' + slug + '"?')) return;

		btn.disabled = true;

		var body = new URLSearchParams();
		body.append('action', 'infomaniak_ai_delete_command');
		body.append('_ajax_nonce', infomaniakAiAdmin.nonce);
		body.append('slug', slug);

		fetch(infomaniakAiAdmin.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.success) {
					var row = btn.closest('tr');
					if (row) row.remove();

					// If no more rows, reload to show empty state.
					var tbody = list.querySelector('tbody');
					if (tbody && tbody.children.length === 0) {
						window.location.reload();
					}
				} else {
					alert(data.data || 'Delete failed.');
					btn.disabled = false;
				}
			})
			.catch(function () {
				alert('Request failed.');
				btn.disabled = false;
			});
	});
})();

/* ==========================================================================
   Commands — Form Save + Live Preview
   ========================================================================== */
(function () {
	'use strict';

	var form = document.getElementById('ik-command-form');
	if (!form || typeof infomaniakAiAdmin === 'undefined') return;

	var promptEl     = document.getElementById('ik-cmd-prompt');
	var previewVars  = document.getElementById('ik-preview-vars');
	var previewSchema = document.getElementById('ik-preview-schema');

	// --- Live Preview ---
	function updatePreview() {
		var template = promptEl.value;
		var regex = /\{\{\s*(\w+)\s*\}\}/g;
		var match;
		var vars = [];
		var seen = {};

		while ((match = regex.exec(template)) !== null) {
			if (!seen[match[1]]) {
				vars.push(match[1]);
				seen[match[1]] = true;
			}
		}

		if (vars.length === 0) {
			previewVars.textContent = 'No variables detected.';
			previewSchema.textContent = '{}';
			return;
		}

		previewVars.textContent = vars.join(', ');

		var properties = {};
		vars.forEach(function (v) {
			properties[v] = { type: 'string', description: v };
		});

		var schema = {
			type: 'object',
			properties: properties,
			required: vars,
		};

		previewSchema.textContent = JSON.stringify(schema, null, 2);
	}

	if (promptEl && previewVars && previewSchema) {
		promptEl.addEventListener('input', updatePreview);
		// Initial render.
		updatePreview();
	}

	// --- Form Save ---
	var saveBtn  = document.getElementById('ik-cmd-save');
	var feedback = document.getElementById('ik-cmd-feedback');

	if (!saveBtn) return;

	var saveBtnLabel = saveBtn.textContent;

	saveBtn.addEventListener('click', function () {
		saveBtn.disabled = true;
		saveBtn.textContent = saveBtn.dataset.saving;
		feedback.className = 'ik-feedback';
		feedback.textContent = '';

		var body = new URLSearchParams();
		body.append('action', 'infomaniak_ai_save_command');
		body.append('_ajax_nonce', infomaniakAiAdmin.nonce);
		body.append('is_new', document.getElementById('ik-cmd-is-new').value);
		body.append('slug', document.getElementById('ik-cmd-slug').value);
		body.append('label', document.getElementById('ik-cmd-label').value);
		body.append('description', document.getElementById('ik-cmd-description').value);
		body.append('prompt_template', document.getElementById('ik-cmd-prompt').value);
		body.append('system_prompt', document.getElementById('ik-cmd-system').value);
		body.append('temperature', document.getElementById('ik-cmd-temperature').value);
		body.append('max_tokens', document.getElementById('ik-cmd-max-tokens').value);
		body.append('category', document.getElementById('ik-cmd-category').value);
		body.append('permission', document.getElementById('ik-cmd-permission').value);
		body.append('model_type', document.getElementById('ik-cmd-model-type').value);
		body.append('model', document.getElementById('ik-cmd-model').value);
		body.append('conversational', document.getElementById('ik-cmd-conversational').checked ? '1' : '0');

		fetch(infomaniakAiAdmin.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.success && data.data && data.data.redirect) {
					window.location.href = data.data.redirect;
				} else {
					feedback.textContent = data.data || 'Save failed.';
					feedback.className = 'ik-feedback ik-feedback--error';
					saveBtn.disabled = false;
					saveBtn.textContent = saveBtnLabel;
				}
			})
			.catch(function () {
				feedback.textContent = 'Request failed.';
				feedback.className = 'ik-feedback ik-feedback--error';
				saveBtn.disabled = false;
				saveBtn.textContent = saveBtnLabel;
			});
	});
})();

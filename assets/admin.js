(function () {
	'use strict';

	var btn = document.getElementById('cryptocon-brella-test-connection');
	var resultEl = document.getElementById('cryptocon-brella-test-result');
	var form = document.getElementById('cryptocon-brella-settings-form');

	if (!btn || !resultEl || !window.cryptoconBrellaAdmin) {
		return;
	}

	function getFieldValue(id) {
		var el = document.getElementById(id);
		return el ? el.value.trim() : '';
	}

	function renderResult(type, html) {
		resultEl.className = 'cryptocon-brella-test-result is-' + type;
		resultEl.innerHTML = html;
	}

	btn.addEventListener('click', function () {
		var apiKey = getFieldValue('brella_api_key');
		var orgId = getFieldValue('brella_organization_id');
		var eventId = getFieldValue('brella_event_id');

		if (!orgId || !eventId) {
			renderResult('error', '<p>' + cryptoconBrellaAdmin.i18n.missing + '</p>');
			return;
		}

		btn.disabled = true;
		renderResult('success', '<p>' + cryptoconBrellaAdmin.i18n.testing + '</p>');

		var body = new URLSearchParams();
		body.append('action', 'cryptocon_brella_test_connection');
		body.append('nonce', cryptoconBrellaAdmin.nonce);
		body.append('api_key', apiKey);
		body.append('organization_id', orgId);
		body.append('event_id', eventId);

		fetch(cryptoconBrellaAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				if (json.success && json.data) {
					var d = json.data;
					var html = '<p><strong>' + (d.message || 'Connection successful') + '</strong></p>';
					html += '<p>Total timeslots: ' + (d.total_count || '0') + '</p>';
					if (d.sample_title || d.sample_start) {
						html += '<p>Sample: ' + (d.sample_title || '(no title)') + (d.sample_start ? ' — ' + d.sample_start : '') + '</p>';
					}
					html += '<p>API version: ' + (d.api_version || 'v4') + '</p>';
					renderResult('success', html);
				} else {
					var err = json.data || {};
					var errHtml = '<p><strong>' + (err.message || cryptoconBrellaAdmin.i18n.error) + '</strong></p>';
					if (err.hint) {
						errHtml += '<p>' + err.hint + '</p>';
					}
					renderResult('error', errHtml);
				}
			})
			.catch(function () {
				renderResult('error', '<p>' + cryptoconBrellaAdmin.i18n.error + '</p>');
			})
			.finally(function () {
				btn.disabled = false;
			});
	});
})();

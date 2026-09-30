/* Brella Agenda for Bricks: tabs, detail dialog, live badges, mobile list mode. */
(function () {
	'use strict';

	function initTabs(root) {
		var tabs = Array.prototype.slice.call(root.querySelectorAll('.ba-tab'));
		if (!tabs.length) return;

		function select(tab, focus) {
			tabs.forEach(function (t) {
				var on = t === tab;
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.tabIndex = on ? 0 : -1;
				var panel = document.getElementById(t.getAttribute('aria-controls'));
				if (panel) {
					panel.hidden = !on;
					panel.classList.toggle('is-active', on);
				}
			});
			if (focus) tab.focus();
		}

		root._baSelectDay = function (panelId) {
			var t = tabs.filter(function (x) { return x.getAttribute('aria-controls') === panelId; })[0];
			if (t) select(t, false);
		};

		// Sync panels with whichever tab the server marked as selected.
		var current = tabs.filter(function (t) { return t.getAttribute('aria-selected') === 'true'; })[0] || tabs[0];
		select(current, false);

		tabs.forEach(function (tab, i) {
			tab.addEventListener('click', function () { select(tab, false); });
			tab.addEventListener('keydown', function (e) {
				var next = null;
				if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
				if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
				if (e.key === 'Home') next = tabs[0];
				if (e.key === 'End') next = tabs[tabs.length - 1];
				if (next) {
					e.preventDefault();
					select(next, true);
				}
			});
		});
	}

	function initDialog(root) {
		var dialog = root.querySelector('.ba-dialog');
		if (!dialog || typeof dialog.showModal !== 'function') return;

		var body = dialog.querySelector('.ba-dialog__body');
		var lastTrigger = null;

		root.addEventListener('click', function (e) {
			var btn = e.target.closest('.ba-session__open');
			if (!btn || !root.contains(btn)) return;
			var card = btn.closest('.ba-session');
			var tpl = card && card.querySelector('template.ba-session__detail');
			if (!tpl) return;

			body.innerHTML = '';
			body.appendChild(tpl.content.cloneNode(true));

			var title = body.querySelector('[data-dialog-title]');
			if (title) title.id = body.id.replace('-body', '-title');

			var accent = card.style.getPropertyValue('--ba-accent');
			if (accent) dialog.style.setProperty('--ba-accent', accent);

			lastTrigger = btn;
			dialog.showModal();
		});

		dialog.querySelector('.ba-dialog__close').addEventListener('click', function () {
			dialog.close();
		});

		// Click on the backdrop closes the dialog.
		dialog.addEventListener('click', function (e) {
			if (e.target === dialog) dialog.close();
		});

		dialog.addEventListener('close', function () {
			if (lastTrigger) lastTrigger.focus();
		});
	}

	function initFilters(root) {
		var selects = Array.prototype.slice.call(root.querySelectorAll('select[data-filter]'));
		if (!selects.length) return;

		var clear = root.querySelector('.ba-filters__clear');
		var status = root.querySelector('.ba-filter-status');
		var sessions = Array.prototype.slice.call(root.querySelectorAll('.ba-session'));
		var days = Array.prototype.slice.call(root.querySelectorAll('.ba-day'));

		function has(list, value) {
			return (' ' + (list || '') + ' ').indexOf(' ' + value + ' ') !== -1;
		}

		function rowSpan(el) {
			var parts = (el.style.gridRow || '').split('/');
			var a = parseInt(parts[0], 10);
			var b = (parts[1] || '').trim();
			var end = b.indexOf('span') === 0 ? a + parseInt(b.slice(4), 10) : parseInt(b, 10);
			return [a, end];
		}

		function trimRows(grid, visible) {
			var rows = parseInt(grid.dataset.rows, 10);
			var labels = grid.querySelectorAll('.ba-time, .ba-line');

			if (!visible || !visible.length) {
				grid.style.gridTemplateRows = 'auto repeat(' + rows + ', var(--ba-slot-h))';
				Array.prototype.forEach.call(labels, function (l) { l.classList.remove('is-filtered-out'); });
				return;
			}

			var lo = Infinity, hi = -Infinity;
			Array.prototype.forEach.call(visible, function (el) {
				var r = rowSpan(el);
				lo = Math.min(lo, r[0]);
				hi = Math.max(hi, r[1]);
			});

			// Snap outwards to the nearest time labels so the range starts on a label.
			var starts = Array.prototype.map.call(grid.querySelectorAll('.ba-time'), function (t) { return rowSpan(t)[0]; });
			var before = starts.filter(function (st) { return st <= lo; });
			var after = starts.filter(function (st) { return st >= hi; });
			var from = before.length ? Math.max.apply(null, before) : 2;
			var to = after.length ? Math.min.apply(null, after) : rows + 2;

			var tpl = ['auto'];
			for (var r = 2; r < rows + 2; r++) {
				tpl.push(r >= from && r < to ? 'var(--ba-slot-h)' : '0px');
			}
			grid.style.gridTemplateRows = tpl.join(' ');

			Array.prototype.forEach.call(labels, function (l) {
				var st = rowSpan(l)[0];
				l.classList.toggle('is-filtered-out', st < from || st >= to);
			});
		}

		function apply() {
			var f = {};
			var active = false;
			selects.forEach(function (sel) {
				f[sel.dataset.filter] = sel.value;
				if (sel.value) active = true;
				sel.parentNode.classList.toggle('is-set', !!sel.value);
			});

			var shown = 0;
			sessions.forEach(function (el) {
				var ok = (!f.track || el.dataset.track === f.track) &&
					(!f.speaker || has(el.dataset.speakers, f.speaker)) &&
					(!f.tag || has(el.dataset.tags, f.tag)) &&
					(!f.type || el.dataset.type === f.type);
				el.classList.toggle('is-filtered-out', !ok);
				if (ok) shown++;
			});

			var firstWithMatches = null;
			var currentEmpty = false;

			days.forEach(function (day) {
				var grid = day.querySelector('.ba-grid');
				var visible = day.querySelectorAll('.ba-session:not(.is-filtered-out)');
				var liveTracks = {};
				Array.prototype.forEach.call(visible, function (el) { liveTracks[el.dataset.track] = true; });

				// Collapse track columns with nothing left to show.
				if (grid && grid.dataset.cols) {
					var cols = JSON.parse(grid.dataset.cols);
					var colTracks = JSON.parse(grid.dataset.colTracks || '[]');
					grid.style.gridTemplateColumns = cols.map(function (c, i) {
						return !active || i === 0 || liveTracks[colTracks[i]] ? c : '0px';
					}).join(' ');
					Array.prototype.forEach.call(grid.querySelectorAll('.ba-track-head'), function (h) {
						h.classList.toggle('is-filtered-out', active && !liveTracks[h.dataset.track]);
					});
				}

				// Collapse the time range to the window the matches sit in.
				if (grid && grid.dataset.rows) {
					trimRows(grid, active ? visible : null);
				}

				var empty = visible.length === 0;
				var msg = day.querySelector('.ba-empty');
				var scroll = day.querySelector('.ba-scroll');
				if (msg) msg.hidden = !empty;
				if (scroll) scroll.classList.toggle('is-filtered-out', empty);

				var tab = root.querySelector('.ba-tab[aria-controls="' + day.id + '"]');
				if (tab) tab.classList.toggle('is-empty', active && empty);

				if (!empty && !firstWithMatches) firstWithMatches = day;
				if (empty && day.classList.contains('is-active')) currentEmpty = true;
			});

			// If the visible day has no matches, jump to the first day that does.
			if (active && currentEmpty && firstWithMatches && root._baSelectDay) {
				root._baSelectDay(firstWithMatches.id);
			}

			if (clear) clear.hidden = !active;
			if (status) {
				status.textContent = active
					? 'Showing ' + shown + ' of ' + sessions.length + ' sessions'
					: '';
			}
		}

		selects.forEach(function (sel) { sel.addEventListener('change', apply); });
		if (clear) {
			clear.addEventListener('click', function () {
				selects.forEach(function (sel) { sel.value = ''; });
				apply();
				selects[0].focus();
			});
		}

		apply();
	}

	function initLive(root) {
		var cards = root.querySelectorAll('.ba-session[data-start]');
		function tick() {
			var now = Date.now();
			cards.forEach(function (c) {
				var live = now >= +c.dataset.start && now < +c.dataset.end;
				c.classList.toggle('is-live', live);
			});
		}
		tick();
		root._baLive = setInterval(tick, 60000);
	}

	/**
	 * Calendar or list. The visitor's choice from the switch wins on wider
	 * screens; below the breakpoint the list is automatic.
	 */
	function initView(root) {
		var bp = root.dataset.mobile === 'list' ? (parseInt(root.dataset.breakpoint, 10) || 0) : 0;
		var buttons = Array.prototype.slice.call(root.querySelectorAll('.ba-view__btn'));
		var storeKey = 'brellaAgendaView';
		var view = root.dataset.defaultView === 'list' ? 'list' : 'calendar';
		var width = root.getBoundingClientRect().width;

		if (buttons.length) {
			try {
				var saved = window.localStorage.getItem(storeKey);
				if (saved === 'list' || saved === 'calendar') view = saved;
			} catch (e) { /* storage unavailable */ }
		}

		var compactAt = parseInt(root.dataset.compactBar, 10) || 0;

		function apply() {
			var narrow = bp > 0 && width < bp;
			root.classList.toggle('is-compact-bar', compactAt > 0 && width < compactAt);
			var list = narrow || view === 'list';
			var changed = root.classList.contains('is-list') !== list || root.classList.contains('is-narrow') !== narrow;
			root.classList.toggle('is-list', list);
			root.classList.toggle('is-narrow', narrow);
			buttons.forEach(function (b) {
				b.setAttribute('aria-pressed', b.dataset.view === view ? 'true' : 'false');
			});
			if (changed) root.dispatchEvent(new CustomEvent('ba:layout'));
		}

		buttons.forEach(function (b) {
			b.addEventListener('click', function () {
				view = b.dataset.view;
				try { window.localStorage.setItem(storeKey, view); } catch (e) { /* ignore */ }
				apply();
			});
		});

		apply();

		if ('ResizeObserver' in window) {
			new ResizeObserver(function (entries) {
				width = entries[0].contentRect.width;
				apply();
			}).observe(root);
		} else {
			window.addEventListener('resize', function () {
				width = root.getBoundingClientRect().width;
				apply();
			});
		}
	}

	function initBreakout(root) {
		if (root.dataset.breakout !== '1') return;
		var min = parseInt(root.dataset.breakoutMin, 10) || 991;

		function update() {
			var on = window.innerWidth > min && !root.classList.contains('is-list');
			root.classList.toggle('is-breakout', on);
			if (!on) {
				root.style.removeProperty('--ba-breakout');
				return;
			}
			// Measure the root (still the container width) to the viewport edge,
			// excluding the page scrollbar so no page-level sideways scroll appears.
			var right = document.documentElement.clientWidth - root.getBoundingClientRect().right;
			root.style.setProperty('--ba-breakout', Math.max(0, Math.round(right)) + 'px');
		}

		update();
		window.addEventListener('resize', update);
		root.addEventListener('ba:layout', update);
		if ('ResizeObserver' in window) new ResizeObserver(update).observe(root);
	}

	/**
	 * Keep the theatre headers pinned while the page scrolls. When the agenda
	 * has its own height (the grid scrolls inside), CSS sticky does the job and
	 * this stays out of the way.
	 */
	function initFreezeHead(root) {
		if (!root.classList.contains('ba-freeze-head')) return;

		var fixedOffset = root.dataset.freezeOffset;
		var queued = false;

		// Space taken by a sticky or fixed site header (Bricks or otherwise).
		function topOffset() {
			if (fixedOffset !== undefined && fixedOffset !== '') return parseInt(fixedOffset, 10) || 0;
			var header = document.querySelector('#brx-header, header.site-header, body > header');
			if (!header) return 0;
			var pos = window.getComputedStyle(header).position;
			if (pos !== 'fixed' && pos !== 'sticky') return 0;
			var r = header.getBoundingClientRect();
			return r.bottom > 0 && r.top <= 0 ? Math.round(r.bottom) : 0;
		}

		function reset(grid) {
			grid.classList.remove('is-head-stuck');
			Array.prototype.forEach.call(grid.querySelectorAll('.ba-track-head, .ba-corner'), function (el) {
				el.style.transform = '';
			});
		}

		function update() {
			queued = false;
			Array.prototype.forEach.call(root.querySelectorAll('.ba-grid'), function (grid) {
				var day = grid.closest('.ba-day');
				var scroll = grid.closest('.ba-scroll');
				var inner = scroll && scroll.scrollHeight > scroll.clientHeight + 1;

				if (root.classList.contains('is-list') || !day || !day.classList.contains('is-active') || inner) {
					reset(grid);
					return;
				}

				var heads = grid.querySelectorAll('.ba-track-head, .ba-corner');
				if (!heads.length) return;

				var rect = grid.getBoundingClientRect();
				var headH = heads[0].offsetHeight;
				var y = Math.min(Math.max(topOffset() - rect.top, 0), Math.max(0, rect.height - headH * 2));

				grid.classList.toggle('is-head-stuck', y > 0);
				Array.prototype.forEach.call(heads, function (el) {
					el.style.transform = y > 0 ? 'translateY(' + Math.round(y) + 'px)' : '';
				});
			});
		}

		function queue() {
			if (queued) return;
			queued = true;
			window.requestAnimationFrame(update);
		}

		window.addEventListener('scroll', queue, { passive: true });
		window.addEventListener('resize', queue);
		root.addEventListener('click', queue);
		root.addEventListener('change', queue);
		root.addEventListener('ba:layout', queue);
		root._baFreezeUpdate = queue;
		queue();
	}

	function init(root) {
		if (root.dataset.baReady) return;
		root.dataset.baReady = '1';
		initTabs(root);
		initFilters(root);
		initDialog(root);
		initLive(root);
		initView(root);
		initBreakout(root);
		initFreezeHead(root);
	}

	// Global so Bricks can re-run it after the element renders in the builder.
	window.brellaAgendaInit = function () {
		document.querySelectorAll('.brella-agenda').forEach(init);
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', window.brellaAgendaInit);
	} else {
		window.brellaAgendaInit();
	}
})();

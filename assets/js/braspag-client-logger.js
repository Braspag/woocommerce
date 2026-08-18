(function (window, document) {
	'use strict';

	if (typeof window.wc_braspag_client_log_params === 'undefined') {
		return;
	}

	var params = window.wc_braspag_client_log_params;
	var queue = [];
	var flushTimer = null;
	var FLUSH_DELAY = 3000;
	var MAX_QUEUE = 25;

	function enqueue(level, message) {
		if (queue.length >= MAX_QUEUE) {
			return;
		}
		queue.push({ level: level, message: String(message).substring(0, 2000) });
		scheduleFlush();
	}

	function scheduleFlush() {
		if (flushTimer) {
			return;
		}
		flushTimer = window.setTimeout(flush, FLUSH_DELAY);
	}

	function flush() {
		flushTimer = null;
		if (!queue.length) {
			return;
		}

		var entries = queue.splice(0, queue.length);
		var body = new window.FormData();
		body.append('action', 'braspag_client_log');
		body.append('nonce', params.nonce);
		body.append('entries', JSON.stringify(entries));

		if (window.navigator && typeof window.navigator.sendBeacon === 'function') {
			window.navigator.sendBeacon(params.ajax_url, body);
			return;
		}

		window.fetch(params.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		}).catch(function () {});
	}

	var originalError = window.console.error;
	var originalWarn = window.console.warn;

	window.console.error = function () {
		enqueue('error', Array.prototype.slice.call(arguments).join(' '));
		return originalError.apply(window.console, arguments);
	};

	window.console.warn = function () {
		enqueue('warn', Array.prototype.slice.call(arguments).join(' '));
		return originalWarn.apply(window.console, arguments);
	};

	window.addEventListener('error', function (event) {
		enqueue('error', event.message + ' @ ' + event.filename + ':' + event.lineno);
	});

	window.addEventListener('unhandledrejection', function (event) {
		var reason = event.reason && event.reason.message ? event.reason.message : event.reason;
		enqueue('unhandledrejection', reason);
	});

	window.addEventListener('beforeunload', flush);
})(window, document);

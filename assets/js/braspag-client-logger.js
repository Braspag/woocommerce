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
	var MPI_LOG_PREFIXES = ['[MPI]', '[MPI-EVENT]', '[BP-DEBUG]'];

	function stringifyArg(arg) {
		if (typeof arg === 'string') {
			return arg;
		}
		if (arg instanceof Error) {
			return arg.message + (arg.stack ? '\n' + arg.stack : '');
		}
		if (arg !== null && typeof arg === 'object') {
			try {
				return JSON.stringify(arg);
			} catch (e) {
				return String(arg);
			}
		}
		return String(arg);
	}

	function formatArgs(args) {
		return Array.prototype.map.call(args, stringifyArg).join(' ');
	}

	function isMpiLogMessage(message) {
		return MPI_LOG_PREFIXES.some(function (prefix) {
			return message.indexOf(prefix) === 0;
		});
	}

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
	var originalLog = window.console.log;

	window.console.error = function () {
		enqueue('error', formatArgs(arguments));
		return originalError.apply(window.console, arguments);
	};

	window.console.warn = function () {
		enqueue('warn', formatArgs(arguments));
		return originalWarn.apply(window.console, arguments);
	};

	// console.log override: captura apenas logs com prefixos do MPI/3DS para evitar ruído
	// (jQuery, Cardinal/Forter fingerprinting e outros scripts também usam console.log)
	window.console.log = function () {
		var message = formatArgs(arguments);
		if (isMpiLogMessage(message)) {
			enqueue('log', message);
		}
		return originalLog.apply(window.console, arguments);
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

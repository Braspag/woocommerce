/**
 * Bloqueio de UI (UX only) para a incompatibilidade SilentOrderPost (SOP) x 3DS.
 *
 * A regra de negócio ("3DS ativo bloqueia SOP, sempre, e vice-versa") é
 * garantida de verdade no servidor, em
 * WC_Gateway_Braspag::process_admin_options() (veja includes/class-wc-gateway-braspag.php).
 * Este script só melhora a experiência: evita que o usuário marque uma opção
 * já sabendo, sem precisar dar submit, que ela será bloqueada no save.
 *
 * Como SOP e 3DS vivem em telas diferentes (braspag / braspag_creditcard /
 * braspag_debitcard), cada tela só pode reagir ao estado JÁ SALVO do "outro
 * lado" (recebido via wp_localize_script em wc_braspag_admin_settings_params),
 * não ao que está sendo digitado na outra tela em outra aba/momento.
 */
(function ($) {
	'use strict';

	if (typeof wc_braspag_admin_settings_params === 'undefined') {
		return;
	}

	var params = wc_braspag_admin_settings_params;

	var FIELD_IDS = {
		braspag: '#woocommerce_braspag_silentpost_enabled',
		braspag_creditcard: '#woocommerce_braspag_creditcard_auth3ds20_mpi_is_active',
		braspag_debitcard: '#woocommerce_braspag_debitcard_auth3ds20_mpi_is_active'
	};

	function insertNotice($field, message) {
		var $existing = $field.closest('tr').prev('.braspag-sop-3ds-notice-row');

		if ($existing.length) {
			$existing.find('.notice-warning p').text(message);
			$existing.show();
			return;
		}

		var $tr = $field.closest('tr');
		var colspan = $tr.find('th, td').length || 2;

		var $row = $(
			'<tr class="braspag-sop-3ds-notice-row"><td colspan="' + colspan + '">' +
			'<div class="notice notice-warning inline" style="margin:0 0 10px;"><p></p></div>' +
			'</td></tr>'
		);

		$row.find('.notice-warning p').text(message);
		$tr.before($row);
	}

	function removeNotice($field) {
		$field.closest('tr').prev('.braspag-sop-3ds-notice-row').hide();
	}

	function blockField($field, message) {
		$field
			.prop('checked', false)
			.prop('disabled', true)
			.attr('title', message)
			.css('opacity', '0.5');

		insertNotice($field, message);
	}

	function unblockField($field) {
		$field
			.prop('disabled', false)
			.removeAttr('title')
			.css('opacity', '');

		removeNotice($field);
	}

	function guardCheckbox($field, isConflicting, message) {
		if (!$field.length) {
			return;
		}

		// Estado inicial: se o "outro lado" já está ativo, desabilita de cara.
		if (isConflicting) {
			blockField($field, message);
			return;
		}

		// Nenhum conflito salvo: mantém habilitado, mas ainda assim mostra o
		// aviso dinamicamente se o usuário tentar marcar (defesa extra client-side,
		// útil quando o "outro lado" muda na mesma sessão antes de recarregar).
		$field.on('change.braspagSop3ds', function () {
			if ($field.is(':checked')) {
				insertNotice($field, message);
			} else {
				removeNotice($field);
			}
		});
	}

	$(function () {
		var section = params.section;
		var $field = $(FIELD_IDS[section]);

		if (!$field.length) {
			return;
		}

		if ('braspag' === section) {
			guardCheckbox($field, !!params.auth3ds_active, params.i18n.sop_blocked_by_3ds);
		} else if ('braspag_creditcard' === section || 'braspag_debitcard' === section) {
			guardCheckbox($field, !!params.sop_enabled, params.i18n.auth3ds_blocked_by_sop);
		}
	});
})(jQuery);

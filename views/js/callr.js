/**
*  @author Callr SAS <integrations@callr.com>
*  @copyright  2016 Callr SAS
*  @license    https://opensource.org/licenses/MIT
*/
$(document).ready(function() {

	// SMS tester markup
	$('#module_form_submit_btn_3').after('<span id="result"></span>');

	// SMS tester Ajax
	$('#module_form_submit_btn_3').on('click', function(e) {
		if ($(this).hasClass('disabled')) return;
		var result = $('#result');
		var inputs = $('#fieldset_3_3 input, #fieldset_3_3 textarea');
		var button = $('#module_form_submit_btn_3');
		button.addClass('disabled');
		inputs.prop('disabled', true);
	 	result.stop(true, true);
	 	result.html('<i class="icon-circle-o-notch icon-spin"></i>');
	 	result.show();
		var url = window.location.href;
		var data = {
		  'ajax': true,
		  'action': 'SmsTester',
		  'number': $('#test_phone').val(),
		  'message': $('#test_message').val(),
		  'username': $('#callr_username').val(),
		  'password': $('#callr_password').val(),
		  'sender': $('#callr_sender').val(),
		  'debug': $('#callr_debug').prop('checked')
		}
		$.post(url, data, function(response) {
			if (response == '1') {
				result.html('<i class="icon-check success"></i>');
			} else {
				result.html('<i class="icon-times error"></i>');
			}
		})
		.always(function() {
  		inputs.prop('disabled', false);
  		button.removeClass('disabled');
  		result.fadeOut(5000);
		});
		e.preventDefault();
	});

	// Toggle
 	$('input:checkbox').each(function() {
 		var id = $(this).attr('id');
 		toggle(id);
    $(this).on('click', function() { toggle(id); });
	});
	function toggle(id) {
		if (id === 'admin_enabled') {
			if ($('#' + id).prop('checked')) {
				$('#admin_phone').parent().parent().show();
				$('#admin_message').parent().parent().show();
				$('#fieldset_0 .alert').show();
			} else {
				$('#admin_phone').parent().parent().hide();
				$('#admin_message').parent().parent().hide();
				$('#fieldset_0 .alert').hide();
			}
		} else if (id.substring(0, 21) === 'customer_notification') {
			var status = id.substring(22);
			if ($('#' + id).prop('checked')) {
				$('#customer_message_' + status).parent().parent().show();
			} else {
				$('#customer_message_' + status).parent().parent().hide();
			}
			if ($('#fieldset_1_1 input:checkbox:checked').length === 0) {
				$('#customer_message_default').parent().parent().hide();
				$('#fieldset_1_1 .alert').hide();
			} else {
				$('#customer_message_default').parent().parent().show();
				$('#fieldset_1_1 .alert').show();
			}
		}
	}

});
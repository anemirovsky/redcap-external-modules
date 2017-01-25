$(function(){
	var disabledModal = $('#external-modules-disabled-modal');
	var enableModal = $('#external-modules-enable-modal');

	var reloadPage = function(){
		$('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
		  var loc = window.location;
		  window.location = loc.protocol + '//' + loc.host + loc.pathname + loc.search;
	}

	disabledModal.find('.enable-button').click(function(event){
		disabledModal.hide();

		var row = $(event.target).closest('tr');
		var prefix = row.data('module');
		var version = row.find('select').val();

		  if (pid) {
			 var enableButton = enableModal.find('.enable-button');
			 enableButton.html('Enable');
			 enableModal.find('button').attr('disabled', false);

			 var list = enableModal.find('.modal-body ul');
			 list.html('');

			 disabledModules[prefix][version].permissions.forEach(function(permission){
				 list.append("<li>" + permission + "</li>");
			 });

			 enableButton.off('click') // disable any events attached from other modules
			 enableButton.click(function(){
				  enableButton.html('Enabling...');
				  enableModal.find('button').attr('disabled', true);

				 $.post('ajax/enable-module.php', {prefix: prefix, version: version}, function (data) {
					 if (data == 'success') {
						 reloadPage();
						 disabledModal.modal('hide');
					 }
					 else {
						 var message = 'An error occurred while enabling the module: ' + data;
						 console.log('AJAX Request Error:', message);
						 alert(message);
					 }

					 enableModal.modal('hide');
				 });
			 });
		  } else {   // pid
			   var data = {};
			   data['<?=ExternalModules::KEY_ENABLED?>'] = true;
			   $.post('ajax/save-settings.php?pid=' + pid + '&moduleDirectoryPrefix=' + prefix, data, function(data){
				if (data.status == 'success') {
					     console.log(JSON.stringify(data));
					reloadPage();
					disabledModal.modal('hide');
				}
				else {
					var message = 'An error occurred while enabling the module: ' + data;
					console.log('AJAX Request Error:', message);
					alert(message);
				}
			   });
		  }

		  if (enableModal) {
			 enableModal.modal('show');
		  }

		return false;
	});

	 if (enableModal) {
		 enableModal.on('hide.bs.modal', function(){
			 if($('#external-modules-disabled-table tr').length === 0){
				 // Reload since there aren't any more disabled modules to enable.
				 reloadPage();
			 }
			 else{
				 disabledModal.show();
			 }
		 });
	 }
});

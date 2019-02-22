function ajaxImport(startingIndex, numberOfIndToProcess,successes,errors, call) {
	jQuery('#loading').show();
	jQuery.ajax({
		url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend
		type: "POST",
		data: {
			'action': 'asyncImport',
			'startingIndex': startingIndex,
			'numberOfIndexesToProcess': numberOfIndToProcess,
			'successes':successes,
			'errors':errors,
			'call': call
		},
		success: function (data) { // This outputs the result of the ajax request
			var obj = JSON.parse(data);
			var startingIndex=obj.startingIndex;
			var CurrentSyncCount=obj.CurrentSyncCountMessage;
			var successes=obj.SuccessCount;
			var errors=obj.ErrorsCount;
			var message=obj.successMessage;

			if (message.includes('continue')) {
				jQuery('#loading h1').html(CurrentSyncCount);
				ajaxImport(startingIndex, numberOfIndToProcess,successes,errors, call);//new ajax call
			}
			if (message.includes('TestFinishedSuccessfully')) {
				jQuery('#loading h1').html("Current Sync Count: 100%");
				setTimeout(function () { jQuery('#loading').hide(); }, 2000);
				location.reload();
			}
		},

		error: function (errorThrown) {
			alert('error on import');
		}
	});
}
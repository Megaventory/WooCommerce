function ajaxInitialize(block, startingInd, numberOfIndToProcess, call) {
	jQuery('#loading').show();
	jQuery.ajax({
		url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend
		type: "POST",
		data: {
            'action': 'asyncImport',
            'block': block,
			'startingIndex': startingInd,
			'numberOfIndexesToProcess': numberOfIndToProcess,
			'call': call
		},
		success: function (data) { // This outputs the result of the ajax request
			var obj = JSON.parse(data);
			var block = obj.block;
			var startingInd = obj.startingIndex;
			var message= obj.successMessage;
			var percentMessage = obj.percentMessage;

			if (message.includes('continue')) {
				jQuery('#loading h1').html(percentMessage);
				ajaxInitialize(block, startingInd, numberOfIndToProcess, call);//new ajax call
			}

			if (message.includes('TestFinishedSuccessfully')) {
				jQuery('#loading h1').html("Current Sync Count: 100%");
				setTimeout(function () { jQuery('#loading').hide(); jQuery('body>*').css("filter","none"); }, 2000);
				location.reload();
			}
		},

		error: function (errorThrown) {
			alert('error on import');
		}
	});
}
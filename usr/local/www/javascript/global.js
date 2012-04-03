var AjaxQueue = {
	batchSize: 1, //No.of simultaneous AJAX requests allowed, Default : 1
	urlQueue: [], //Request URLs will be pushed into this array
	elementsQueue: [], //Element IDs of elements to be updated on completion of a request
	optionsQueue: [], //Request options will be pushed into this array
	currentRequest: null,
	setBatchSize: function(bSize){ //Method to set a different batch size. Recommended: Set batchSize before making requests
		this.batchSize = bSize;
	},
	push: function(url, options, elementID){ //Push the request in the queue. elementID is optional and required only for Ajax requests that updates the element
		this.urlQueue.push(url);
		this.optionsQueue.push(options);
		if(elementID!=null){
			this.elementsQueue.push(elementID);
		} else {
			this.elementsQueue.push("NOTSPECIFIED");
		}

		this._processNext();
	},
	_processNext: function() { // Method for processing the requests in the queue. Private method. Don't call it explicitly
		if(this.currentRequest == null && this.urlQueue.length > 0) // Check if the currently processing request count is less than batch size
		{
			// Call jQuery.ajax on the first item in the queue and remove it from the queue
			AjaxQueue.currentRequest = jQuery.ajax(AjaxQueue.urlQueue.shift(), AjaxQueue.optionsQueue.shift()); 
			AjaxQueue.currentRequest.complete( function() {
				//Call AjaxQueue._processNext on completion ( success / failure) of this AJAX request.
				AjaxQueue.currentRequest = null;
				AjaxQueue._processNext();
			});
			if(this.elementsQueue[0]=="NOTSPECIFIED") { //Check if an elementID was specified
				// If no ElementID was specified remove the first item from the queue
				var junk = AjaxQueue.elementsQueue.shift();
			} else {
				// If ElementID was specified update the first item in the queue and remove it from the queue
				AjaxQueue.currentRequest.success( function(data) {
					jQuery(AjaxQueue.elementsQueue.shift()).html(data);
				});
			}
		}
	}
};


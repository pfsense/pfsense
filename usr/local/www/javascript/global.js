var AjaxQueue = {
	batchSize: 1, //No.of simultaneous AJAX requests allowed, Default : 1
	urlQueue: [], //Request URLs will be pushed into this array
	elementsQueue: [], //Element IDs of elements to be updated on completion of a request ( as in Ajax.Updater )
	optionsQueue: [], //Request options will be pushed into this array
	setBatchSize: function(bSize){ //Method to set a different batch size. Recommended: Set batchSize before making requests
		this.batchSize = bSize;
	},
	push: function(url, options, elementID){ //Push the request in the queue. elementID is optional and required only for Ajax.Updater calls
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
		if(Ajax.activeRequestCount < AjaxQueue.batchSize) // Check if the currently processing request count is less than batch size
		{	
			if(AjaxQueue.elementsQueue.first()=="NOTSPECIFIED") { //Check if an elementID was specified
				// Call Ajax.Request if no ElementID specified
				//Call Ajax.Request on the first item in the queue and remove it from the queue
				new Ajax.Request(AjaxQueue.urlQueue.shift(), AjaxQueue.optionsQueue.shift()); 
				
				var junk = AjaxQueue.elementsQueue.shift();
			} else {
				// Call Ajax.Updater if an ElementID was specified.
				//Call Ajax.Updater on the first item in the queue and remove it from the queue
				new Ajax.Updater(AjaxQueue.elementsQueue.shift(), AjaxQueue.urlQueue.shift(), AjaxQueue.optionsQueue.shift());				
			}		
		}
	}
};
Ajax.Responders.register({
  //Call AjaxQueue._processNext on completion ( success / failure) of any AJAX call.
  onComplete: AjaxQueue._processNext
});

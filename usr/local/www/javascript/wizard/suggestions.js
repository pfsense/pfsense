
/**
 * Provides suggestions for state names (USA).
 * @class
 * @scope public
 */
function StateSuggestions(text) {
    this.states = text;
}

/**
 * Request suggestions for the given autosuggest control. 
 * @scope protected
 * @param oAutoSuggestControl The autosuggest control to provide suggestions for.
 */
StateSuggestions.prototype.requestSuggestions = function (oAutoSuggestControl /*:AutoSuggestControl*/,
                                                          bTypeAhead /*:boolean*/) {
    var aSuggestions = [];
    var sTextboxValue = oAutoSuggestControl.textbox.value;
    
    if (sTextboxValue.length > 0){
    
        //search for matching states
        for (var i=0; i < this.states.length; i++) { 
            if (this.states[i].indexOf(sTextboxValue) == 0) {
                aSuggestions.push(this.states[i]);
            } 
        }
    }

    //provide suggestions to the control
    oAutoSuggestControl.autosuggest(aSuggestions, bTypeAhead);
};
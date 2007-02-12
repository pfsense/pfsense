/** $Id$ */
// Title: Fadomatic
// Version: 1.2
// Homepage: http://chimpen.com/fadomatic
// Author: Philip McCarthy <fadomatic@chimpen.com>

// Fade interval in milliseconds
// Make this larger if you experience performance issues
Fadomatic.INTERVAL_MILLIS = 50;

// Creates a fader
// element - The element to fade
// speed - The speed to fade at, from 0.0 to 100.0
// initialOpacity (optional, default 100) - element's starting opacity, 0 to 100
// minOpacity (optional, default 0) - element's minimum opacity, 0 to 100
// maxOpacity (optional, default 0) - element's minimum opacity, 0 to 100
function Fadomatic (element, rate, initialOpacity, minOpacity, maxOpacity) {
  this._element = element;
  this._intervalId = null;
  this._rate = rate;
  this._isFadeOut = true;

  // Set initial opacity and bounds
  // NB use 99 instead of 100 to avoid flicker at start of fade
  this._minOpacity = 0;
  this._maxOpacity = 99;
  this._opacity = 99;

  if (typeof minOpacity != 'undefined') {
    if (minOpacity < 0) {
      this._minOpacity = 0;
    } else if (minOpacity > 99) {
      this._minOpacity = 99;
    } else {
      this._minOpacity = minOpacity;
    }
  }

  if (typeof maxOpacity != 'undefined') {
    if (maxOpacity < 0) {
      this._maxOpacity = 0;
    } else if (maxOpacity > 99) {
      this._maxOpacity = 99;
    } else {
      this._maxOpacity = maxOpacity;
    }

    if (this._maxOpacity < this._minOpacity) {
      this._maxOpacity = this._minOpacity;
    }
  }
  
  if (typeof initialOpacity != 'undefined') {
    if (initialOpacity > this._maxOpacity) {
      this._opacity = this._maxOpacity;
    } else if (initialOpacity < this._minOpacity) {
      this._opacity = this._minOpacity;
    } else {
      this._opacity = initialOpacity;
    }
  }

  // See if we're using W3C opacity, MSIE filter, or just
  // toggling visiblity
  if(typeof element.style.opacity != 'undefined') {

    this._updateOpacity = this._updateOpacityW3c;

  } else if(typeof element.style.filter != 'undefined') {

    // If there's not an alpha filter on the element already,
    // add one
    if (element.style.filter.indexOf("alpha") == -1) {

      // Attempt to preserve existing filters
      var existingFilters="";
      if (element.style.filter) {
        existingFilters = element.style.filter+" ";
      }
      element.style.filter = existingFilters+"alpha(opacity="+this._opacity+")";
    }

    this._updateOpacity = this._updateOpacityMSIE;
    
  } else {

    this._updateOpacity = this._updateVisibility;
  }

  this._updateOpacity();
}

// Initiates a fade out
Fadomatic.prototype.fadeOut = function () {
  this._isFadeOut = true;
  this._beginFade();
}

// Initiates a fade in
Fadomatic.prototype.fadeIn = function () {
  this._isFadeOut = false;
  this._beginFade();
}

// Makes the element completely opaque, stops any fade in progress
Fadomatic.prototype.show = function () {
  this.haltFade();
  this._opacity = this._maxOpacity;
  this._updateOpacity();
}

// Makes the element completely transparent, stops any fade in progress
Fadomatic.prototype.hide = function () {
  this.haltFade();
  this._opacity = 0;
  this._updateOpacity();
}

// Halts any fade in progress
Fadomatic.prototype.haltFade = function () {

  clearInterval(this._intervalId);
}

// Resumes a fade where it was halted
Fadomatic.prototype.resumeFade = function () {

  this._beginFade();
}

// Pseudo-private members

Fadomatic.prototype._beginFade = function () {

  this.haltFade();
  var objref = this;
  this._intervalId = setInterval(function() { objref._tickFade(); },Fadomatic.INTERVAL_MILLIS);
}

Fadomatic.prototype._tickFade = function () {

  if (this._isFadeOut) {
    this._opacity -= this._rate;
    if (this._opacity < this._minOpacity) {
      this._opacity = this._minOpacity;
      this.haltFade();
    }
  } else {
    this._opacity += this._rate;
    if (this._opacity > this._maxOpacity ) {
      this._opacity = this._maxOpacity;
      this.haltFade();
    }
  }

  this._updateOpacity();
}

Fadomatic.prototype._updateVisibility = function () {
  
  if (this._opacity > 0) {
    this._element.style.visibility = 'visible';
  } else {
    this._element.style.visibility = 'hidden';
  }
}

Fadomatic.prototype._updateOpacityW3c = function () {
  
  this._element.style.opacity = this._opacity/100;
  this._updateVisibility();
}

Fadomatic.prototype._updateOpacityMSIE = function () {
  
  this._element.filters.alpha.opacity = this._opacity;
  this._updateVisibility();
}

Fadomatic.prototype._updateOpacity = null;

<?php
/* $Id$ */
/* DISABLE_PHP_LINT_CHECKING                                                  */
/* ========================================================================== */
/*
  menu.inc
  Copyright (C) 2006 Daniel S. Haischt <me@daniel.stefan.haischt.name>
  All rights reserved.
                                                                              */
/* ========================================================================== */
/*
  Originally part of m0n0wall (http://m0n0.ch/wall)
  Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
  All rights reserved.

  Redistribution and use in source and binary forms, with or without
  modification, are permitted provided that the following conditions are met:

  1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

  2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

  THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
  INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
  AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
  AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
  OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
  INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
  CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
  POSSIBILITY OF SUCH DAMAGE.
                                                                              */
/* ========================================================================== */

require("menu.inc");

define("TMENU_STUB", "
	// set up drop downs anywhere in the body of the page. I think the bottom of the page is better.. 
	// but you can experiment with effect on loadtime.
	if (TransMenu.isSupported()) {

		//==================================================================================================
		// create a set of dropdowns
		//==================================================================================================
		// the first param should always be down, as it is here
		//
		// The second and third param are the top and left offset positions of the menus from their actuators
		// respectively. To make a menu appear a little to the left and bottom of an actuator, you could use
		// something like -5, 5
		//
		// The last parameter can be .topLeft, .bottomLeft, .topRight, or .bottomRight to inidicate the corner
		// of the actuator from which to measure the offset positions above. Here we are saying we want the 
		// menu to appear directly below the bottom left corner of the actuator
		//==================================================================================================
		var ms = new TransMenuSet(TransMenu.direction.down, 1, 0, TransMenu.reference.bottomLeft);
                
		//==================================================================================================
		// create a dropdown menu
		//==================================================================================================
		// the first parameter should be the HTML element which will act actuator for the menu
		//==================================================================================================
                
                @@MENU_DEFINITIONS@@
                
		//==================================================================================================
		// write drop downs into page
		//==================================================================================================
		// this method writes all the HTML for the menus into the page with document.write(). It must be
		// called within the body of the HTML page.
		//==================================================================================================
		TransMenu.renderAll();
	}
");

class NervecenterMenu extends Menu {
  private $menuJScript = "NOT-SET";
  private $menuJScriptEvents = "NOT-SET";
  private $menuID = "NOT-SET";
  
  public function __construct($identification = "", $filename = "", Component $c = NULL) {
    parent::__construct($identification, $filename, $c);
    
    $id = "mnua_" . str_replace(" ", "", strtolower($this->getID()));
    $this->menuID = $id;
    
    if ($this->hasParent()) {
      $this->menuJScript = "\tvar {$id} = ms.addMenu(document.getElementById('{$id}'));\n";
    }
  } // end __construct
    
  public function getMenuID() {
    return $this->menuID;
  }
  
  public function setMenuID($myMenuID) {
    $this->menuID = $myMenuID;
  }
  
  public function getMenuJScript() {
    $childJScript = "";
    foreach ($this->getChildren() as $component) {
      $childJScript .= $component->getMenuJScript();
    }
    
    if (! $this->hasParent()) {
      $this->menuJScript = str_replace("@@MENU_DEFINITIONS@@", $childJScript, TMENU_STUB);
    } else {
      $this->menuJScript .= "\n$childJScript\n";
    }
    
    return $this->menuJScript;
  }
  
  public function getMenuJScriptEvents() {
    return $this->menuJScriptEvents;
  }

  public function __toString() {
    if (! $this->hasParent()) {
      $menuMarkup =<<<EOD
        <div id="menu">
          @@CHILD_ELEMENTS@@
        </div>

EOD;
    } else {    
      $name = gettext($this->getID());
      $id = "mnua_" . str_replace(" ", "", strtolower($this->getID()));
      
      $menuMarkup =<<<EOD
        <a id="{$id}" href="#">{$name}</a>
        @@CHILD_ELEMENTS@@  
EOD;
    }
    
    $childMarkup = "";
    foreach ($this->getChildren() as $component) {
      $childMarkup .= $component;
    }

    $menuMarkup = str_replace("@@CHILD_ELEMENTS@@", $childMarkup, $menuMarkup);

    return $menuMarkup;
  }
}

class NervecenterMenuItem extends MenuItem {
  private $menuJScript = "NOT-SET";
  private $menuJScriptEvents = "NOT-SET";
  
  public function __construct($identification = "", $filename = "", $href = "", Component $c = NULL) {
    parent::__construct($identification, $filename, $href, $c);
    
    $href = $this->getHref() <> "" ? $this->getHref() : "{$this->getFile()}";
    $name = gettext($this->getID());
    $file = $this->getFile();
    
    $this->menuJScript = $this->check_access("{$file}", "\t{$this->getParent()->getMenuID()}.addItem('{$name}', '{$href}');\n");
  }
  
  public function getMenuJScript() {    
    return $this->menuJScript;
  }
  
  public function getMenuJScriptEvents() {
    return $this->menuJScriptEvents;
  }

  public function __toString() {
    return "";
  }
}

?>

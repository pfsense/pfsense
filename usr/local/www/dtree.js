function navigate(url) {
	location.href=url;
}

// getElementByID with browser check!!

function getElById(idVal) {
  if (document.getElementById != null)
    return document.getElementById(idVal)
  if (document.all != null)
    return document.all[idVal]
  
  alert("Problem getting element by id")
  return null
}


// Node object

function Node(id, pid, name, url, title, target, icon, iconOpen, open , checkable ,checked, add, del) {

	this.id = id;

	this.pid = pid;

	this.name = name;

	this.url = url;

	this.title = title;

	this.target = target;

	this.icon = icon;

	this.iconOpen = iconOpen;

	this._io = open || false;
	
	this.checkable = checkable || false;
	
	this.checked = checked || false;
	
	this.add = add || false;
	
	this.del = del || false;

	this._is = false;

	this._ls = false;

	this._hc = false;

	this._ai = 0;

	this._p;

};

// Tree object

function dTree(objName) {

	this.config = {

		target				: null,

		folderLinks			: true,

		useSelection		: true,

		useCookies			: true,

		useLines			: true,

		useIcons			: true,

		useStatusText		: false,

		closeSameLevel		: false,

		inOrder				: false

	}

	this.icon = {

		root				: 'images/zone.gif',

		folder				: 'img/folder.gif',

		folderOpen			: 'img/folderopen.gif',

		node				: 'images/page.gif',

		empty				: 'images/empty.gif',

		line				: 'images/line.gif',

		join				: 'images/join.gif',

		joinBottom			: 'images/joinbottom.gif',

		plus				: 'images/plus_updown.gif',

		plusBottom			: 'images/plus_up.gif',

		minus				: 'images/minus_.gif',

		minusBottom			: 'images/minus_up.gif',

		nlPlus				: 'images/plus_.gif',

		nlMinus				: 'images/minus_.gif'

	};

	this.obj = objName;

	this.aNodes = [];

	this.aIndent = [];

	this.root = new Node(-1);

	this.selectedNode = null;

	this.selectedFound = false;

	this.completed = false;

};



// Adds a new node to the node array

dTree.prototype.add = function(id, pid, name, url, title, target, icon, iconOpen, open, checkable, checked, add, del) {

	this.aNodes[this.aNodes.length] = new Node(id, pid, name, url, title, target, icon, iconOpen, open, checkable, checked, add, del);

};



// Open/close all nodes

dTree.prototype.openAll = function() {

	this.oAll(true);

};

dTree.prototype.closeAll = function() {

	this.oAll(false);

};



// Outputs the tree to the page

dTree.prototype.toString = function() {

	var str = '<div class="treeview">\n';

	if (document.getElementById) {

		if (this.config.useCookies) this.selectedNode = this.getSelected();

		str += this.addNode(this.root);

	} else str += 'Browser not supported.';

	str += '</div>';

	if (!this.selectedFound) this.selectedNode = null;

	this.completed = true;

	return str;

};



// Creates the tree structure

dTree.prototype.addNode = function(pNode) {

	var str = '';

	var n=0;

	if (this.config.inOrder) n = pNode._ai;

	for (n; n<this.aNodes.length; n++) {

		if (this.aNodes[n].pid == pNode.id) {

			var cn = this.aNodes[n];

			cn._p = pNode;

			cn._ai = n;

			this.setCS(cn);

			if (!cn.target && this.config.target) cn.target = this.config.target;

			if (cn._hc && !cn._io && this.config.useCookies) cn._io = this.isOpen(cn.id);

			if (!this.config.folderLinks && cn._hc) cn.url = null;

			if (this.config.useSelection && cn.id == this.selectedNode && !this.selectedFound) {

					cn._is = true;

					this.selectedNode = n;

					this.selectedFound = true;

			}

			str += this.node(cn, n);

			if (cn._ls) break;

		}

	}

	return str;

};


// Creates the node icon, url and text

dTree.prototype.node = function(node, nodeId) {

	var str = '<div class="node" >' + this.indent(node, nodeId);

	if (this.config.useIcons) {

		if (!node.icon) node.icon = (this.root.id == node.pid) ? this.icon.root : ((node._hc) ? this.icon.folder : this.icon.node);

		if (!node.iconOpen) node.iconOpen = (node._hc) ? this.icon.folderOpen : this.icon.node;

		if (this.root.id == node.pid) {

			node.icon = this.icon.root;

			node.iconOpen = this.icon.root;

		}

		//class="img"  border="10" vspace="18" align="bottom" style="border:0px; margin:0; padding:0; FILTER: progid:DXImageTransform.Microsoft.Alpha(style=0,opacity=50); -moz-opacity:0.5" 
		str += '<img  style="border:0; margin:0; padding:0; height:16px; vertical-align:top; overflow:hidden;"  id="i' + this.obj + nodeId + '" src="' + ((node._io) ? node.iconOpen : node.icon) + '" alt="" />';
		if (node.checkable) {
			str += '<input style="border:0; margin:0; padding:0; height:16px; vertical-align:top; overflow:hidden; FILTER: progid:DXImageTransform.Microsoft.Alpha(style=0,opacity=50); " type="checkbox" id="c' + this.obj + node.id + '" onclick="javascript : ' + this.obj + '.c(' + node.id + ');"';
			if (node.checked) str += ' checked >'; else str += ' >';
		}//else str+='';

	}

	if (node.url) {

		str += '<a id="s' + this.obj + nodeId + '" class="' + ((this.config.useSelection) ? ((node._is ? 'nodeSel' : 'node')) : 'node') + '" href="javascript:navigate(\'' + node.url + '\')"';

		if (node.title) str += ' title="' + node.title + '"';

		//if (node.target) str += ' target="' + node.target + ';"';

		if (this.config.useStatusText) str += ' onmouseover="window.status=\'' + node.name + '\';return true;" onmouseout="window.status=\'\';return true;" ';

		if (this.config.useSelection && ((node._hc && this.config.folderLinks) || !node._hc))

			str += ' onclick="javascript: ' + this.obj + '.s(' + nodeId + ');"';

		str += '>';

	}

	else if ((!this.config.folderLinks || !node.url) && node._hc && node.pid != this.root.id)

		str += '<a style="border:0; margin:0; padding:0; text-size: smaller; vertical-align: top; overflow:hidden;"  href="javascript: ' + this.obj + '.o(' + nodeId + ');" class="node">';

	str += '  '+node.name;

	if (node.url || ((!this.config.folderLinks || !node.url) && node._hc)) str += '</a>';
	
	if (node.add && node.url) {
		str += '   -- <a href="' + node.url + '&qaction=addsub"/> <img  style="border:0; margin:0; padding:0;  width: 16px; height:16px; vertical-align: top; overflow:hidden;"  id="i' + this.obj + nodeId + '" src="images/plus.gif" alt="" /></a>';
	}
	if (node.del && node.url) {
		str += '    <a href="' + node.url + '&qaction=delete"/>  <img  style="border:0; margin:0; padding:0; width: 11px; height:11px; vertical-align: top; overflow:hidden;"  id="i' + this.obj + nodeId + '" src="images/block.gif" alt="" /></a>';
	}

	if (node._hc) {

		str += '<div id="d' + this.obj + nodeId + '" class="clip" style="display:' + ((this.root.id == node.pid || node._io) ? 'block' : 'none') + ';">';

		str += this.addNode(node);

		str += '</div>';

	}
	
	str += '</div>';

	this.aIndent.pop();

	return str;

};


// Adds the empty and line icons

dTree.prototype.indent = function(node, nodeId) {

	var str = '';

	if (this.root.id != node.pid) {

		for (var n=0; n<this.aIndent.length; n++)

			str += '<img  style="verical-align: top;" src="' + ( (this.aIndent[n] == 1 && this.config.useLines) ? this.icon.line : this.icon.empty ) + '" alt="" />';

		(node._ls) ? this.aIndent.push(0) : this.aIndent.push(1);

		if (node._hc) {

			str += '<a href="javascript: ' + this.obj + '.o(' + nodeId + ');"><img id="j' + this.obj + nodeId + '" src="';

			if (!this.config.useLines) str += (node._io) ? this.icon.nlMinus : this.icon.nlPlus;

			else str += ( (node._io) ? ((node._ls && this.config.useLines) ? this.icon.minusBottom : this.icon.minus) : ((node._ls && this.config.useLines) ? this.icon.plusBottom : this.icon.plus ) );

			str += '" alt="" /></a>';

		} else str += '<img src="' + ( (this.config.useLines) ? ((node._ls) ? this.icon.joinBottom : this.icon.join ) : this.icon.empty) + '" alt="" />';

	}

	return str;

};



// Checks if a node has any children and if it is the last sibling

dTree.prototype.setCS = function(node) {

	var lastId;

	for (var n=0; n<this.aNodes.length; n++) {

		if (this.aNodes[n].pid == node.id) node._hc = true;

		if (this.aNodes[n].pid == node.pid) lastId = this.aNodes[n].id;

	}

	if (lastId==node.id) node._ls = true;

};



// Returns the selected node

dTree.prototype.getSelected = function() {

	var sn = this.getCookie('cs' + this.obj);

	return (sn) ? sn : null;

};



// Highlights the selected node

dTree.prototype.s = function(id) {

	if (!this.config.useSelection) return;

	var cn = this.aNodes[id];

	if (cn._hc && !this.config.folderLinks) return;

	if (this.selectedNode != id) {

		if (this.selectedNode || this.selectedNode==0) {

			eOld = document.getElementById("s" + this.obj + this.selectedNode);

			eOld.className = "node";

		}

		eNew = document.getElementById("s" + this.obj + id);

		eNew.className = "nodeSel";

		this.selectedNode = id;

		if (this.config.useCookies) this.setCookie('cs' + this.obj, cn.id);

	}

};

// Checks all parents

dTree.prototype.c = function(id) {

	//alert('callid='+id);
	
	var n=0;
	
	var parent;
	
	parent = document.getElementById("c" + this.obj + id);
	
	//if (parent.checked==true) parent.checked=false; else parent.checked=true;
	
	var what = parent.checked;

	for (n; n<this.aNodes.length; n++) {

		if (this.aNodes[n].pid == id) {

			var cn = this.aNodes[n];
			
			element = document.getElementById("c" + this.obj + cn.id);
			
			element.checked = what;
			
			cn.checked = what;
			
			//alert(cn.pid+'(child)==(parent)'+id+'->'+cn.id+'='+what+','+this.aNodes.length );
			
			this.c(cn.id);

		}

	}

};
	
	
dTree.prototype.generate_checkedlist = function(id) {
	
	var retStr='';
	
	var n=0;
	
	var parent = document.getElementById("c" + this.obj + id);

	for (n; n<this.aNodes.length; n++) {

		if (this.aNodes[n].pid == id) {

			var cn = this.aNodes[n];
			
			var element = document.getElementById("c" + this.obj + cn.id);
			
			//alert(cn.name + ' = ' +element.checked);
			
			if (element.checked) {
				
				if (retStr == '') 
					{retStr = cn.name; }// this.generate_checkedlist(cn.id); 
				else
					retStr += ',' + cn.name;//this.generate_checkedlist(cn.id);
			}
			//alert(cn.pid+'(child)==(parent)'+id+'->'+cn.id+'='+what+','+this.aNodes.length );
			
		}

	}
	
	return retStr;
}

// Toggle Open or close

dTree.prototype.o = function(id) {

	var cn = this.aNodes[id];

	this.nodeStatus(!cn._io, id, cn._ls);

	cn._io = !cn._io;

	if (this.config.closeSameLevel) this.closeLevel(cn);

	if (this.config.useCookies) this.updateCookie();

};



// Open or close all nodes

dTree.prototype.oAll = function(status) {

	for (var n=0; n<this.aNodes.length; n++) {

		if (this.aNodes[n]._hc && this.aNodes[n].pid != this.root.id) {

			this.nodeStatus(status, n, this.aNodes[n]._ls)

			this.aNodes[n]._io = status;

		}

	}

	if (this.config.useCookies) this.updateCookie();

};



// Opens the tree to a specific node

dTree.prototype.openTo = function(nId, bSelect, bFirst) {

	if (!bFirst) {

		for (var n=0; n<this.aNodes.length; n++) {

			if (this.aNodes[n].id == nId) {

				nId=n;

				break;

			}

		}

	}

	var cn=this.aNodes[nId];

	if (cn.pid==this.root.id || !cn._p) return;

	cn._io = true;

	cn._is = bSelect;

	if (this.completed && cn._hc) this.nodeStatus(true, cn._ai, cn._ls);

	if (this.completed && bSelect) this.s(cn._ai);

	else if (bSelect) this._sn=cn._ai;

	this.openTo(cn._p._ai, false, true);

};



// Closes all nodes on the same level as certain node

dTree.prototype.closeLevel = function(node) {

	for (var n=0; n<this.aNodes.length; n++) {

		if (this.aNodes[n].pid == node.pid && this.aNodes[n].id != node.id && this.aNodes[n]._hc) {

			this.nodeStatus(false, n, this.aNodes[n]._ls);

			this.aNodes[n]._io = false;

			this.closeAllChildren(this.aNodes[n]);

		}

	}

}



// Closes all children of a node

dTree.prototype.closeAllChildren = function(node) {

	for (var n=0; n<this.aNodes.length; n++) {

		if (this.aNodes[n].pid == node.id && this.aNodes[n]._hc) {

			if (this.aNodes[n]._io) this.nodeStatus(false, n, this.aNodes[n]._ls);

			this.aNodes[n]._io = false;

			this.closeAllChildren(this.aNodes[n]);		

		}

	}

}



// Change the status of a node(open or closed)

dTree.prototype.nodeStatus = function(status, id, bottom) {

	eDiv	= document.getElementById('d' + this.obj + id);

	eJoin	= document.getElementById('j' + this.obj + id);

	if (this.config.useIcons) {

		eIcon	= document.getElementById('i' + this.obj + id);

		eIcon.src = (status) ? this.aNodes[id].iconOpen : this.aNodes[id].icon;

	}

	eJoin.src = (this.config.useLines)?

	((status)?((bottom)?this.icon.minusBottom:this.icon.minus):((bottom)?this.icon.plusBottom:this.icon.plus)):

	((status)?this.icon.nlMinus:this.icon.nlPlus);

	eDiv.style.display = (status) ? 'block': 'none';

};





// [Cookie] Clears a cookie

dTree.prototype.clearCookie = function() {

	var now = new Date();

	var yesterday = new Date(now.getTime() - 1000 * 60 * 60 * 24);

	this.setCookie('co'+this.obj, 'cookieValue', yesterday);

	this.setCookie('cs'+this.obj, 'cookieValue', yesterday);

};



// [Cookie] Sets value in a cookie

dTree.prototype.setCookie = function(cookieName, cookieValue, expires, path, domain, secure) {

	document.cookie =

		escape(cookieName) + '=' + escape(cookieValue)

		+ (expires ? '; expires=' + expires.toGMTString() : '')

		+ (path ? '; path=' + path : '')

		+ (domain ? '; domain=' + domain : '')

		+ (secure ? '; secure' : '');

};



// [Cookie] Gets a value from a cookie

dTree.prototype.getCookie = function(cookieName) {

	var cookieValue = '';

	var posName = document.cookie.indexOf(escape(cookieName) + '=');

	if (posName != -1) {

		var posValue = posName + (escape(cookieName) + '=').length;

		var endPos = document.cookie.indexOf(';', posValue);

		if (endPos != -1) cookieValue = unescape(document.cookie.substring(posValue, endPos));

		else cookieValue = unescape(document.cookie.substring(posValue));

	}

	return (cookieValue);

};



// [Cookie] Returns ids of open nodes as a string

dTree.prototype.updateCookie = function() {

	var str = '';

	for (var n=0; n<this.aNodes.length; n++) {

		if (this.aNodes[n]._io && this.aNodes[n].pid != this.root.id) {

			if (str) str += '.';

			str += this.aNodes[n].id;

		}

	}

	this.setCookie('co' + this.obj, str);

};



// [Cookie] Checks if a node id is in a cookie

dTree.prototype.isOpen = function(id) {

	var aOpen = this.getCookie('co' + this.obj).split('.');

	for (var n=0; n<aOpen.length; n++)

		if (aOpen[n] == id) return true;

	return false;

};



// If Push and pop is not implemented by the browser

if (!Array.prototype.push) {

	Array.prototype.push = function array_push() {

		for(var i=0;i<arguments.length;i++)

			this[this.length]=arguments[i];

		return this.length;

	}

};

if (!Array.prototype.pop) {

	Array.prototype.pop = function array_pop() {

		lastElement = this[this.length-1];

		this.length = Math.max(this.length-1,0);

		return lastElement;

	}

};

function fr_toggle(id, prefix) {
	if (!prefix)
		prefix = 'fr';

	var checkbox = document.getElementById(prefix + 'c' + id);
	checkbox.checked = !checkbox.checked;
	fr_bgcolor(id, prefix);
}

function fr_bgcolor(id, prefix) {
	if (!prefix)
		prefix = 'fr';

	var row = document.getElementById(prefix + id);
	var checkbox = document.getElementById(prefix + 'c' + id);
	var cells = row.getElementsByTagName('td');
	var cellcnt = cells.length;

	for (i = 0; i < cellcnt; i++) {
		// Check for cells with frd id only
		if (cells[i].id == prefix + 'd' + id)
			cells[i].style.backgroundColor = checkbox.checked ? "#FFFFBB" : "#FFFFFF";
	}
	//cells[7].style.backgroundColor = checkbox.checked ? "#FFFFBB" : "#990000";
}

function fr_insline(id, on, prefix) {
	if (!prefix)
		prefix = 'fr';

	var row = document.getElementById(prefix + id);
	var prevrow;
	if (id != 0) {
		prevrow = document.getElementById(prefix + (id-1));
	} else {
		prevrow = document.getElementById(prefix + 'header');
	}

	var cells = row.getElementsByTagName("td");
	var prevcells = prevrow.getElementsByTagName("td");

	for (i = 0; i <= prevcells.length - 1; i++) {
		if (prevcells[i].id == prefix + 'd' + (id-1)) {
			if (on) {
				prevcells[i].style.borderBottom = "3px solid #990000";
				prevcells[i].style.paddingBottom = ((id != 0) ? 2 : 3) + "px";
			} else {
				prevcells[i].style.borderBottom = "1px solid #999999";
				prevcells[i].style.borderBottomWidth = "1px";
				prevcells[i].style.paddingBottom = ((id != 0) ? 4 : 5) + "px";
			}
		}
	}

	for (i = 0; i <= cells.length - 1; i++) {
		if (cells[i].id == prefix + 'd' + (id)) {
			if (on) {
				cells[i].style.borderTop = "2px solid #990000";
				cells[i].style.paddingTop = "2px";
			} else {
				cells[i].style.borderTopWidth = 0;
				cells[i].style.paddingTop = "4px";
			}
		}
	}
}

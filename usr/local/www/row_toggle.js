function fr_toggle(id) {
	var checkbox = document.getElementById('frc' + id);
	checkbox.checked = !checkbox.checked;
	fr_bgcolor(id);
}
function fr_bgcolor(id) {
	var row = document.getElementById('fr' + id);
	var checkbox = document.getElementById('frc' + id);
	var cells = row.getElementsByTagName('td');
	var cellcnt = cells.length;

	for (i = 0; i <= cellcnt; i++) {
		// Check for cells with frd id only
		if (cells[i].id == "frd" + id)
			cells[i].style.backgroundColor = checkbox.checked ? "#FFFFBB" : "#FFFFFF";
	}
	//cells[7].style.backgroundColor = checkbox.checked ? "#FFFFBB" : "#990000";
}
function fr_insline(id, on) {
	var row = document.getElementById('fr' + id);
	if (id != 0) {
		var prevrow = document.getElementById('fr' + (id-1));
	} else {
		var prevrow = document.getElementById('frheader');
	}

	var cells = row.getElementsByTagName("td");
	var prevcells = prevrow.getElementsByTagName("td");

	for (i = 2; i <= 7; i++) {
		if (on) {
			prevcells[i].style.borderBottom = "3px solid #990000";
			prevcells[i].style.paddingBottom = (id != 0) ? 2 : 3;
		} else {
			prevcells[i].style.borderBottom = "1px solid #999999";
			prevcells[i].style.borderBottomWidth = "1px";
			prevcells[i].style.paddingBottom = (id != 0) ? 4 : 5;
		}
	}

	for (i = 2; i <= 7; i++) {
		if (on) {
			cells[i].style.borderTop = "2px solid #990000";
			cells[i].style.paddingTop = 2;
		} else {
			prevcells[i].style.borderBottom = "1px solid #999999";
			cells[i].style.borderTopWidth = 0;
			cells[i].style.paddingTop = 4;
		}
	}
}

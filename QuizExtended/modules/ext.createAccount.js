(function(){
	document.getElementById("wpRealName").required = true;

	var tips = document.getElementsByClassName("htmlform-tip");
	for(var t of tips) {
		if(t.innerText.includes("Real name") && t.innerText.includes("optional")) {t.hidden = true;} // hide the text that says it it optional
	}

	var label = $('label[for="wpRealName"]');
	label.text(label.text().replace(/(?:\s|^)\S*?optional\S*?(?:\s|$)/, "")); // remove the "optional" beside the name
}());

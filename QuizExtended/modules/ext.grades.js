mw.gotoFormUrl = function (context, filter) { // a quick way to filter for something
	var url = window.location.href.split('?');
	url = url[1] && url[1].includes("title=Special:") ? url[0] + `?title=Special:CheckGrades&selection=${filter}&filter=${context.textContent}` : url[0] + `?selection=${filter}&filter=${context.textContent}`;
	setTimeout(()=>{
		window.location.href = url;},
		1);
};
mw.gotoGradeView = function (context, filter) { // check a certain persons grades, from student overview
	var url = window.location.href.split('/');
	url.pop();
	url = url.join('/') + "/Special:CheckGrades";
	url = url + `?selection=${filter}&filter=${context.textContent}`;
	setTimeout(()=>{window.location.href = url;}, 1);
};

mw.toggleDropdown = function (context) {
	var div = context.parentElement;
	div.classList.toggle("dropdown-hide");
	div.classList.toggle("dropdown-show");
};

(function(){ // change a url from "&title=Special:something" to "/Special:something", it breaks some other js
	var txt = window.location.href;
	if (txt.includes("title=Special:")) {
		window.location.href = txt.replace("?title=", "/").replace(/&/, "?");
	}
}());//why mediawiki?
var getUrlParameter = function getUrlParameter(sParam) {
	var sPageURL = decodeURIComponent(window.location.search.substring(1)),
		sURLVariables = sPageURL.split('&'),
		sParameterName,
		i;

	for (i = 0; i < sURLVariables.length; i++) {
		sParameterName = sURLVariables[i].split('=');

		if (sParameterName[0] === sParam) {
			return sParameterName[1] === undefined ? true : sParameterName[1];
		}
	}
};

if(getUrlParameter('start')) {
	var startNum = getUrlParameter('start');
} else {
	if(getUrlParameter('page')) {
		var startNum = (getUrlParameter('page')-1)*25;
	} else {
		var startNum = 0;
	}
}

if(getUrlParameter('postid')) {
	var postid = getUrlParameter('postid');
} else {
  var postid = 0;
}

$(document).ready(function() {
	var rTable = $('#rmb').DataTable( {
		"dom": "lrifptp",
		"processing": true,
		"serverSide": true,
		"ordering": false,
		"language": {
			"processing": "Loading..."
		},
		"order": [[0, "desc"]],
		"ajax": {
			"url": "cw-rmbhandler.php",
			"data": function ( d ) {
				d.postid = postid;
				d.searchAuthor = $('input#rmbArxSAuthor').val();
			}
		},
		"pagingType": "input",
		"info": false,
		"displayStart": startNum,
		"lengthChange": false,
		"pageLength": 25,
		"length": 25,
		"columns": [
			{
				"class": "details-control",
				"data": null,
				"defaultContent": "",
				"visible": false
			},
			{
				"data":	"html",
			}
		],
		"initComplete": function(settings, json) {
			$('#rmb_filter').bind('keyup', function(e) {
			  if(e.keyCode == 13) {
				rTable.search( this.value ).draw();
			  }
			});
		}
	} );
	$('body').on('click', 'span.nlistmore a', function(event) {
		event.preventDefault();
		var $el = $(this).parent();
		$el.hide();
		$el.next().show().css('display', 'inline').find('img').each(function() {
			$(this).attr('src', '/images/flags/' + $(this).attr('data-flag'));
		});
	});
	$('body').on('click', 'button.nscode_spoilerbutton', function(event) {
		event.preventDefault();
		var myText = $(this).parent().find('.nscode_spoilertext');
		var wasVis = myText.is(':visible');
		myText.toggle('fast');
		if (wasVis) {
			$(this).html($(this).attr('data-myText'));
		} else {
			$(this).attr('data-myText', $(this).html());
			$(this).text("Click to hide...");
		}
	});
	$('body').on('click', 'a.rmbshow:not(.noa)', function(event) {
		event.preventDefault();
		var postid = $(this).parents('.rmbrow').attr('class').split('post-')[1];
		$('.suppressedbody-' + postid).toggle('slow');
	});
	$('body').on('click', 'a.rmbbutton.button.rmbquote', function(event) {
		var postid = $(this).parents('.rmbrow').attr('class').split('post-')[1];
		$('#message').load('cw-rmbhandler.php?q='+postid);
		$('#message').show();
	});
} );
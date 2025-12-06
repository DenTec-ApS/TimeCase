/**
 * Application logic available globally throughout the app
 */
var app = {

	/** @var landmarks used to screen-scrape the html error output for a friendly message */
	errorLandmarkStart: '<!-- ERROR ',
	errorLandmarkEnd: ' /ERROR -->',

	/**
	 * Format a date as dd-mm-yy
	 * @param dateObj Date object or date string in yyyy-mm-dd format
	 * @returns formatted date string dd-mm-yy
	 */
	formatDateDDMMYY: function(dateObj) {
		var d = dateObj instanceof Date ? dateObj : this.parseDate(dateObj);
		if (!d || isNaN(d.getTime())) return '';
		var day = ('0' + d.getDate()).slice(-2);
		var month = ('0' + (d.getMonth() + 1)).slice(-2);
		var year = (d.getFullYear() + '').slice(-2);
		return day + '-' + month + '-' + year;
	},

	/**
	 * Display an alert message inside the element with the id containerId
	 *
	 * @param string message to display
	 * @param string style: '', 'alert-error', 'alert-success' or 'alert-info'
	 * @param int timeout for message to hide itself
	 * @param string containerId (default = 'alert')
	 */
	appendAlert: function(message,style, timeout,containerId) {

		if (!style) style = '';
		if (!timeout) timeout = 0;
		if (!containerId) containerId = 'alert';

		var id = _.uniqueId('alert_');

		var html = '<div id="'+id+'" class="alert '+ this.escapeHtml(style) +'" style="display: none;">'
			+ '<a class="close" data-dismiss="alert">&times;</a>'
			+ '<span>'+ this.escapeHtml(message) +'</span>'
			+ '</div>';

		// scroll the alert message into view
		var container = $('#' + containerId);
		container.append(html);
		container.parent().animate({
			scrollTop: container.offset().top - container.parent().offset().top + container.parent().scrollTop() - 10 // (10 is for top padding)
		});
		
		$('#'+id).slideDown('fast');

		if (timeout > 0) {
			setTimeout("app.removeAlert('"+id+"')",timeout);
		}
	},

	/**
	 * Remove an alert that has been previously shown
	 * @param string element id
	 */
	removeAlert: function(id) {

		$("#"+id).slideUp('fast', function(){
			$("#"+id).remove();
		});
	},

	/**
	 * show the progress bar
	 * @param the id of the element containing the progress bar
	 */
	showProgress: function(elementId)
	{
		$('#'+elementId).show();
		// $('#'+elementId).animate({width:'150'},'fast');
	},

	/**
	 * hide the progress bar
	 * @param the id of the element containing the progress bar
	 */
	hideProgress: function(elementId)
	{
		setTimeout("$('#"+elementId+"').hide();",100);
		// $('#'+elementId).animate({width:'0'},'fast');
	},

	/**
	 * Escape unsafe HTML chars to prevent xss injection
	 * @param string potentially unsafe value
	 * @returns string safe value
	 */
	escapeHtml: function(unsafe) {
		return _.escape(unsafe);
	},
	
	/**
	 * return true if user interface should be limited based on browser support
	 * @returns bool
	 */
	browserSucks: function()
	{
		return $.browser.msie && $.browser.version < 9;
	},

	/**
	 * Accept string in the following format: 'YYYY-MM-DD hh:mm:ss' or 'YYYY-MM-DD'
	 * If a date object is padded in, it will be returned as-is
	 * @param string | date:
	 * @param defaultDate if the provided string can't be parsed, return this instead (default is Now)
	 * @returns Date
	 */
	parseDate: function(str, defaultDate) {
		
		// don't re-parse a date obj
		if (str instanceof Date) return str;
		
		if (typeof(str) == 'undefined') defaultDate = new Date();
		
		// if the value passed in was blank, default to today
		if (str == '' || typeof(str) == 'undefined')
		{
			//if (window.console) console.log('app.parseDate: empty or undefined date value');
			return defaultDate;
		}

		var d;
		try
		{
			var dateTime = str.split(' ');
			var dateParts = dateTime[0].split('-');
			var timeParts = dateTime.length > 1 ? dateTime[1].split(':') : ['00','00','00'];
			// pad the time with zeros if it wasn't provided
			while (timeParts.length < 3) timeParts[timeParts.length] = '00';
			d = new Date(dateParts[0], dateParts[1]-1, dateParts[2], timeParts[0], timeParts[1], timeParts[2]);
		}
		catch (error)
		{
			if (window.console) console.log('app.parseDate: ' + error.message);
			d = defaultDate;
		}

		// if either of these occur then the date wasn't parsed correctly
		if ( typeof(d) == 'undefined' || isNaN(d.getTime()) )
		{
			if (window.console) console.log('app.parseDate: unable to parse date value');
			d = defaultDate;
		}
				
		return d;
	},

	/**
	 * Convenience method for creating an option
	 */
	getOptionHtml: function(val,label,selected)
	{
		return '<option value="' + _.escape(val) + '" ' + (selected ? 'selected="selected"' : '') +'>'
			+ _.escape(label)
			+ '</option>'
	},

	/**
	 * A server error should contain json data, but if a fatal php error occurs it
	 * may contain html.  the function will parse the return contents of an
	 * error response and return the error message
	 * @param server response
	 */
	getErrorMessage: function(resp) {

		var msg = 'An unknown error occured';
		try
		{
			var json = $.parseJSON(resp.responseText);
			msg = json.message;
		}
		catch (error)
		{
			// TODO: possibly use regex or some other more robust way to get details...?
			var parts = resp.responseText.split(app.errorLandmarkStart);

			if (parts.length > 1)
			{
				var parts2 = parts[1].split(app.errorLandmarkEnd);
				msg = parts2[0]
			}
		}

		return msg ? msg : 'Unknown server error';
	},

	/**
	 * Format decimal hours to hh:mm format
	 * @param decimal hours value
	 * @returns string in hh:mm format
	 */
	formatHours: function(decimalHours) {
		if (!decimalHours || decimalHours === 0) return '00:00';

		var totalMinutes = Math.round(decimalHours * 60);
		var hours = Math.floor(totalMinutes / 60);
		var minutes = totalMinutes % 60;

		// Pad with zeros
		hours = hours < 10 ? '0' + hours : hours;
		minutes = minutes < 10 ? '0' + minutes : minutes;

		return hours + ':' + minutes;
	},

	version: 1.1

}

/**
 * Initialize modal event handlers to prevent body scrolling on mobile
 * Bootstrap 2.x doesn't automatically add/remove the modal-open class
 */
$(document).on('show', '.modal', function() {
	$('body').addClass('modal-open');
}).on('hidden', '.modal', function() {
	$('body').removeClass('modal-open');
});

/**
 * Fix mobile dropdown collapse issue
 * Auto-expand all dropdowns on mobile so user doesn't need to click them individually
 */
// $(document).ready(function() {
// 	function setupDropdowns() {
// 		if ($(window).width() < 768) {
// 			// On mobile, expand all dropdowns by default and prevent collapsing
// 			$('.nav-collapse .dropdown-menu').show();
// 			$('.nav-collapse .dropdown').addClass('open');
// 		} else {
// 			// On desktop, allow normal dropdown toggle behavior
// 			$('.nav-collapse .dropdown-menu').removeAttr('style');
// 			$('.nav-collapse .dropdown').removeClass('open');
// 		}
// 	}

// 	setupDropdowns();

// 	// Re-run on window resize
// 	$(window).on('resize', setupDropdowns);
// });

/**
 * Fix dropdown positioning in scrollable containers
 * Handle combobox typeahead dropdowns positioning
 */
$(document).ready(function() {
	function positionTypeahead($trigger, $menu) {
		if (!$trigger.length || !$menu.length) return;

		var triggerRect = $trigger[0].getBoundingClientRect();
		var containerRect = $trigger.closest('.combobox-container')[0].getBoundingClientRect();

		// Position relative to viewport
		$menu.css({
			position: 'fixed',
			top: (triggerRect.bottom) + 'px',
			left: containerRect.left + 'px',
			zIndex: 1050
		});
	}

	// Position typeahead when dropdown toggle is clicked
	$(document).on('click', '.combobox-container .dropdown-toggle', function() {
		var $container = $(this).closest('.combobox-container');
		var $menu = $container.find('.typeahead');
		var $trigger = $(this);

		if ($menu.is(':visible')) {
			positionTypeahead($trigger, $menu);
		}
	});

	// Reposition on any scroll event (window, element, or modal scroll)
	$(window).on('scroll resize', function() {
		var $visibleMenus = $('.typeahead:visible');
		$visibleMenus.each(function() {
			var $menu = $(this);
			var $container = $menu.closest('.combobox-container');
			var $trigger = $container.find('.dropdown-toggle');
			positionTypeahead($trigger, $menu);
		});
	});

	// Also listen for scroll on all scrollable parents
	$(document).on('scroll', '*', function() {
		var $visibleMenus = $('.typeahead:visible');
		$visibleMenus.each(function() {
			var $menu = $(this);
			var $container = $menu.closest('.combobox-container');
			var $trigger = $container.find('.dropdown-toggle');
			positionTypeahead($trigger, $menu);
		});
	});

	// Close typeahead when clicking outside
	$(document).on('click', function(e) {
		var $target = $(e.target);
		var $openMenus = $('.typeahead:visible');

		if (!$openMenus.length) return;

		// Don't close if clicking on typeahead or combobox
		if ($target.closest('.typeahead').length || $target.closest('.combobox-container').length) {
			return;
		}

		$openMenus.hide();
	});
});
/**
 * View logic for Reports
 */


var page = {

	timeEntries: new model.ReportsCollection(),
	collectionView: null,
	reports: null,
	modelView: null,
	isInitialized: false,
	isInitializing: false,

	fetchParams: {
		orderBy: '',
		orderDesc: '',
		page: 1,
		filterByTimeStart: '',
		filterByTimeEnd: '',
		filterByCustomer: '',
		filterByProject: '',
		filterByUser: '',
		filterByCategory: '',
		filterByInvoiced: ''},
		
	fetchInProgress: false,
	dialogIsOpen: false,
	isStopButtonClicked: false,

	/**
	 *
	 */
	init: function()
	{
		// ensure initialization only occurs once
		if (page.isInitialized || page.isInitializing) return;
		page.isInitializing = true;
		
		if (!$.isReady && console) console.warn('page was initialized before dom is ready.  views may not render properly.');
		
		app.hideProgress('modelLoader');
		
		// show reports in new window
		$(".showReportButton").click(function(e) {
			e.preventDefault();
			
			var params = page.refreshData(true);
			params.page = '';
			var urlParams = $.param(params);
			var reportType = $(this).attr('data-type');
			
			window.open('report/' + reportType + '?' + urlParams);
			return true;
		});
		
		// selection or time changed
		$("#filterContainer input, #filterContainer select").change(function(e) {
			e.preventDefault();
			app.showProgress('modelLoader');
			
			var currentId = $(this).attr('id');

			// on customer change update projects combo so it displays only related projects
			if (currentId == 'customerId'){
				
				var customerId = $(this).val();
				
				// reset combo select for projectId
				$('#parentProjectId select option').remove();
				$('#parentProjectId ul li').remove();
				
				// populate new dropdown options for projectId based on customerId
				var projectIdValues = new model.ProjectCollection();
				projectIdValues.fetch({
					success: function(c){
						
						$('#projectId *').remove();
						var dd = $('#projectId');						
						dd.append('<option value=""></option>');
						c.forEach(function(item,index)
						{
							// add only projects related to this customer or all in blank
							if (customerId == '' || item.get('customerId') == customerId){
								dd.append(app.getOptionHtml(
										item.get('id'),
										item.get('title'), // TODO: change fieldname if the dropdown doesn't show the desired column
										false // no defaults
									));
							}			
						});
						
						if (!app.browserSucks())
						{
							// refresh bootstrap combo
							dd.data('combobox').refresh()
							$('div.combobox-container + span.help-inline').hide(); // TODO: hack because combobox is making the inline help div have a height
						}
						
						page.refreshData();
						app.hideProgress('modelLoader');
						return true;
						

					},
					error: function(collection,response,scope){
						app.appendAlert(app.getErrorMessage(response), 'alert-error',0,'modelAlert');
						return false;
					}
				});
			}
			
			page.refreshData();
			app.hideProgress('modelLoader');
		});
		

		// init date-pickers
		$('.date-picker')
		.datepicker({ format: 'dd-mm-yy', weekStart: 1 })
		.on('changeDate', function(ev){
			$('.date-picker').datepicker('hide');
		});

		// date range preset buttons - helper functions
	function formatDate(date) {
		var d = new Date(date);
		var day = ('0' + d.getDate()).slice(-2);
		var month = ('0' + (d.getMonth() + 1)).slice(-2);
		var year = (d.getFullYear() + '').slice(-2);
		return day + '-' + month + '-' + year;
	}

	function getMonday(d) {
		d = new Date(d);
		var day = d.getDay(),
			diff = d.getDate() - day + (day === 0 ? -6 : 1);
		return new Date(d.setDate(diff));
	}

	function updatePresetHighlight() {
		var startVal = $('#start').val();
		var endVal = $('#end').val();
		var today = new Date();

		$('.date-preset').each(function() {
			var preset = $(this).data('preset');
			var start, end;

			switch(preset) {
				case 'today':
					start = new Date(today);
					end = new Date(today);
					break;
				case 'yesterday':
					start = new Date(today);
					start.setDate(start.getDate() - 1);
					end = new Date(start);
					break;
				case 'thisweek':
					start = getMonday(today);
					end = new Date(today);
					break;
				case 'lastweek':
					var lastMonday = getMonday(today);
					lastMonday.setDate(lastMonday.getDate() - 7);
					start = lastMonday;
					end = new Date(lastMonday);
					end.setDate(end.getDate() + 6);
					break;
				case 'thismonth':
					start = new Date(today.getFullYear(), today.getMonth(), 1);
					end = new Date(today.getFullYear(), today.getMonth() + 1, 1);
					break;
				case 'lastmonth':
					start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
					end = new Date(today.getFullYear(), today.getMonth(), 0);
					break;
			}

			if (startVal === formatDate(start) && endVal === formatDate(end)) {
				$(this).addClass('active').css('background-color', '#2eac99').css('color', '#ffffff');
			} else {
				$(this).removeClass('active').css('background-color', '').css('color', '');
			}
		});
	}

	// date range preset buttons
		$('.date-preset').on('click', function(e){
			e.preventDefault();
			var preset = $(this).data('preset');
			var today = new Date();
			var start, end;

			function formatDate(date) {
				var d = new Date(date);
				var day = ('0' + d.getDate()).slice(-2);
				var month = ('0' + (d.getMonth() + 1)).slice(-2);
				var year = (d.getFullYear() + '').slice(-2);
				return day + '-' + month + '-' + year;
			}

			function getMonday(d) {
				d = new Date(d);
				var day = d.getDay(),
					diff = d.getDate() - day + (day === 0 ? -6 : 1);
				return new Date(d.setDate(diff));
			}

			switch(preset) {
				case 'today':
					start = new Date(today);
					end = new Date(today);
					break;
				case 'yesterday':
					start = new Date(today);
					start.setDate(start.getDate() - 1);
					end = new Date(start);
					break;
				case 'thisweek':
					start = getMonday(today);
					end = new Date(today);
					break;
				case 'lastweek':
					var lastMonday = getMonday(today);
					lastMonday.setDate(lastMonday.getDate() - 7);
					start = lastMonday;
					end = new Date(lastMonday);
					end.setDate(end.getDate() + 6);
					break;
				case 'thismonth':
					start = new Date(today.getFullYear(), today.getMonth(), 1);
					end = new Date(today.getFullYear(), today.getMonth() + 1, 1);
					break;
				case 'lastmonth':
					start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
					end = new Date(today.getFullYear(), today.getMonth(), 0);
					break;
			}

			if (start && end) {
				$('#start').val(formatDate(start)).datepicker('update');
				$('#end').val(formatDate(end)).datepicker('update');
				updatePresetHighlight();
				page.refreshData();
			}
		});

		// Update highlight when dates change
		$('#start, #end').on('changeDate change', function() {
			updatePresetHighlight();
		});

		// Initial highlight check
		updatePresetHighlight();

		// call comboboxes
		if (!app.browserSucks())
		{
			$('#customerId').combobox();
			$('#projectId').combobox();
			$('#userId').combobox();
			$('#categoryId').combobox();
			$('#invoicedFilter').combobox();
			$('div.combobox-container + span.help-inline').hide(); // TODO: hack because combobox is making the inline help div have a height
		}

			
		// initialize the collection view
		this.collectionView = new view.CollectionView({
			el: $("#timeEntryCollectionContainer"),
			templateEl: $("#timeEntryCollectionTemplate"),
			collection: page.timeEntries
		});

		
		// make the rows clickable ('rendered' is a custom event, not a standard backbone event)
		this.collectionView.on('rendered',function(){

			// make the headers clickable for sorting
 			$('table.collection thead tr th').click(function(e) {
 				
 				if (this.id == 'header_Duration') return;
 				
 				e.preventDefault();
				var prop = this.id.replace('header_','');

				// toggle the ascending/descending before we change the sort prop
				page.fetchParams.orderDesc = (prop == page.fetchParams.orderBy && !page.fetchParams.orderDesc) ? '1' : '';
				page.fetchParams.orderBy = prop;
				page.fetchParams.page = 1;
 				page.fetchTimeEntries(page.fetchParams);
 			});

			// attach click handlers to the pagination controls
			$('.pageButton').click(function(e) {
				e.preventDefault();
				page.fetchParams.page = this.id.substr(5);
				page.fetchTimeEntries(page.fetchParams);
			});

			// handle invoiced checkbox changes in the table
			$('.invoiced-checkbox').on('change', function() {
				var $checkbox = $(this);
				var timeEntryId = parseInt($checkbox.data('id'));
				var isInvoiced = $checkbox.prop('checked') ? 1 : 0;

				// Find the time entry in the collection and update it
				var timeEntry = page.timeEntries.find(function(item) { return parseInt(item.get('id')) === timeEntryId; });
				if (timeEntry) {
					timeEntry.save({'invoiced': isInvoiced}, {
						patch: true,
						wait: true,
						error: function(model, response) {
							// Revert checkbox on error
							$checkbox.prop('checked', !isInvoiced);
							app.appendAlert(app.getErrorMessage(response), 'alert-error', 3000, 'collectionAlert');
						}
					});
				}
			});

			page.isInitialized = true;
			page.isInitializing = false;

		});

		// backbone docs recommend bootstrapping data on initial page load, but we live by our own rules!
		var initStart = $('input#start').val()+' '+$('input#start-time').val();
		var initEnd = $('input#end').val()+' '+$('input#end-time').val();
		this.fetchTimeEntries({ page: 1,  filterByTimeStart: initStart, filterByTimeEnd: initEnd});

	},
	
	refreshData: function(getFiltersOnly)
	{
		var timeStart = $('input#start').val()+' '+$('input#start-time').val();
		if (!$('input#end').val()){
			$('input#end').val($('input#start').val());
		}
		var timeEnd = $('input#end').val()+' '+$('input#end-time').val();
		var customerId = $('#customerId').val();
		var projectId = $('#projectId').val();
		var userId = $('#userId').val();
		var categoryId = $('#categoryId').val();
		var invoicedFilter = $('#invoicedFilter').val();

		page.fetchParams.filterByTimeStart = timeStart;
		page.fetchParams.filterByTimeEnd = timeEnd;
		page.fetchParams.filterByCustomer = customerId;
		page.fetchParams.filterByProject = projectId;
		page.fetchParams.filterByUser = userId;
		page.fetchParams.filterByCategory = categoryId;
		page.fetchParams.filterByInvoiced = invoicedFilter;

		if (getFiltersOnly){
			return page.fetchParams;
		}

		page.fetchParams.page = 1;
		page.fetchTimeEntries(page.fetchParams);
	},

	/**
	 * Fetch the collection data from the server
	 * @param object params passed through to collection.fetch
	 * @param bool true to hide the loading animation
	 */
	fetchTimeEntries: function(params, hideLoader)
	{
		// persist the params so that paging/sorting/filtering will play together nicely
		page.fetchParams = params;

		if (page.fetchInProgress)
		{
			if (window.console) console.log('supressing fetch because it is already in progress');
		}

		page.fetchInProgress = true;

		if (!hideLoader) app.showProgress('loader');;

		page.timeEntries.fetch({

			data: params,

			success: function() {

				if (page.timeEntries.collectionHasChanged)
				{
					// data returned from the server.  render the collection view
					page.timeEntries.render();
				}

				// update total duration
				$('#totalDurationHolder').html(page.timeEntries.totalDuration);
				
				app.hideProgress('loader');
				page.fetchInProgress = false;
			},

			error: function(m, r) {
				app.appendAlert(app.getErrorMessage(r), 'alert-error',0,'collectionAlert');
				app.hideProgress('loader');
				page.fetchInProgress = false;
			}

		});
	}

};


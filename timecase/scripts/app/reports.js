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
					end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
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
					end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
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
		var initStart = page.convertDateToIso($('input#start').val())+' '+$('input#start-time').val();
		var initEnd = page.convertDateToIso($('input#end').val())+' '+$('input#end-time').val();
		this.fetchTimeEntries({ page: 1,  filterByTimeStart: initStart, filterByTimeEnd: initEnd});

	},
	
	convertDateToIso: function(dateStr) {
		// Convert from dd-mm-yy format to yyyy-mm-dd
		if (!dateStr || dateStr.length !== 8) return dateStr;
		var parts = dateStr.split('-');
		if (parts.length !== 3) return dateStr;
		var day = parts[0];
		var month = parts[1];
		var year = parseInt(parts[2], 10);
		// Convert 2-digit year to 4-digit year
		var fullYear = (year < 50) ? '20' + parts[2] : '19' + parts[2];
		return fullYear + '-' + month + '-' + day;
	},

	refreshData: function(getFiltersOnly)
	{
		var startDate = page.convertDateToIso($('input#start').val());
		var timeStart = startDate+' '+$('input#start-time').val();
		if (!$('input#end').val()){
			$('input#end').val($('input#start').val());
		}
		var endDate = page.convertDateToIso($('input#end').val());
		var timeEnd = endDate+' '+$('input#end-time').val();
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

				// render charts
				page.renderCharts();

				app.hideProgress('loader');
				page.fetchInProgress = false;
			},

			error: function(m, r) {
				app.appendAlert(app.getErrorMessage(r), 'alert-error',0,'collectionAlert');
				app.hideProgress('loader');
				page.fetchInProgress = false;
			}

		});
	},

	/**
	 * Aggregate data for charts
	 */
	aggregateChartData: function() {
		var data = {
			timePerCustomer: {},
			timePerUser: {},
			timePerWorktype: {},
			timePerDay: {},
			timePerProject: {},
			invoicedStatus: { invoiced: 0, notInvoiced: 0 }
		};

		page.timeEntries.each(function(entry) {
			var duration = parseInt(entry.get('duration')) || 0;
			var customerName = entry.get('customerName') || 'Unknown';
			var userName = entry.get('userName') || 'Unknown';
			var categoryName = entry.get('categoryName') || 'Unknown';
			var projectTitle = entry.get('projectTitle') || 'Unknown';
			var invoiced = entry.get('invoiced');
			var startDate = entry.get('start');

			// Time per Customer
			if (!data.timePerCustomer[customerName]) {
				data.timePerCustomer[customerName] = 0;
			}
			data.timePerCustomer[customerName] += duration;

			// Time per User
			if (!data.timePerUser[userName]) {
				data.timePerUser[userName] = 0;
			}
			data.timePerUser[userName] += duration;

			// Time per Work Type
			if (!data.timePerWorktype[categoryName]) {
				data.timePerWorktype[categoryName] = 0;
			}
			data.timePerWorktype[categoryName] += duration;

			// Time per Project
			if (!data.timePerProject[projectTitle]) {
				data.timePerProject[projectTitle] = 0;
			}
			data.timePerProject[projectTitle] += duration;

			// Time per Day
			if (startDate) {
				var dayKey = startDate.split(' ')[0];
				if (!data.timePerDay[dayKey]) {
					data.timePerDay[dayKey] = 0;
				}
				data.timePerDay[dayKey] += duration;
			}

			// Invoiced Status
			if (invoiced == 1 || invoiced == '1') {
				data.invoicedStatus.invoiced += duration;
			} else {
				data.invoicedStatus.notInvoiced += duration;
			}
		});

		return data;
	},

	/**
	 * Convert minutes to hours
	 */
	minutesToHours: function(minutes) {
		return (minutes / 60).toFixed(2);
	},

	/**
	 * Fill in missing dates in date range
	 */
	fillMissingDates: function(dataObj) {
		if (Object.keys(dataObj).length === 0) {
			return dataObj;
		}

		var dates = Object.keys(dataObj).sort();
		var startDate = new Date(dates[0]);
		var endDate = new Date(dates[dates.length - 1]);

		var result = {};
		var currentDate = new Date(startDate);

		while (currentDate <= endDate) {
			var dateStr = currentDate.toISOString().split('T')[0];
			result[dateStr] = dataObj[dateStr] || 0;
			currentDate.setDate(currentDate.getDate() + 1);
		}

		return result;
	},

	/**
	 * Render all charts
	 */
	renderCharts: function() {
		if (page.timeEntries.length === 0) {
			$('#chartsContainer').hide();
			$('#chartsEmptyMessage').show();
			return;
		}

		$('#chartsContainer').show();
		$('#chartsEmptyMessage').hide();

		var chartData = page.aggregateChartData();

		// Color palette for charts
		var colors = [
			'#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6',
			'#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#d35400',
			'#c0392b', '#27ae60', '#2980b9', '#8e44ad', '#16a085'
		];

		// Time per Customer
		page.createBarChart('timePerCustomerChart', 'Time per Customer', chartData.timePerCustomer, colors);

		// Time per User
		page.createBarChart('timePerUserChart', 'Time per User', chartData.timePerUser, colors);

		// Time per Work Type
		page.createBarChart('timePerWorktypeChart', 'Time per Work Type', chartData.timePerWorktype, colors);

		// Time per Day
		var filledDayData = page.fillMissingDates(chartData.timePerDay);
		page.createLineChart('timePerWeekChart', 'Time per Day', filledDayData);

		// Time per Project
		page.createBarChart('timePerProjectChart', 'Time per Project', chartData.timePerProject, colors);

		// Invoiced Status
		page.createInvoicedChart('invoicedStatusChart', 'Invoiced Status', chartData.invoicedStatus);
	},

	/**
	 * Create a bar chart
	 */
	createBarChart: function(canvasId, title, data, colors) {
		var ctx = document.getElementById(canvasId).getContext('2d');

		// Destroy existing chart if it exists
		if (page.chartInstances && page.chartInstances[canvasId]) {
			page.chartInstances[canvasId].destroy();
		}
		if (!page.chartInstances) {
			page.chartInstances = {};
		}

		var labels = Object.keys(data);
		var rawMinutes = labels.map(function(label) {
			return data[label];
		});
		var values = rawMinutes.map(function(minutes) {
			return page.minutesToHours(minutes);
		});
		var totalMinutes = rawMinutes.reduce(function(a, b) { return a + b; }, 0);

		page.chartInstances[canvasId] = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [{
					label: 'Hours',
					data: values,
					backgroundColor: colors.slice(0, labels.length),
					borderColor: colors.slice(0, labels.length),
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				interaction: {
					mode: 'nearest',
					intersect: false
				},
				scales: {
					y: {
						beginAtZero: true
					}
				},
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						enabled: true,
						backgroundColor: 'rgba(0, 0, 0, 0.8)',
						padding: 12,
						titleFont: {
							size: 13,
							weight: 'bold'
						},
						bodyFont: {
							size: 12
						},
						callbacks: {
							title: function(context) {
								return context[0].label;
							},
							label: function(context) {
								var hours = context.parsed.y;
								var minutes = rawMinutes[context.dataIndex];
								return [
									'Hours: ' + hours,
									'Minutes: ' + minutes
								];
							},
							afterLabel: function(context) {
								var percentage = ((rawMinutes[context.dataIndex] / totalMinutes) * 100).toFixed(1);
								return 'Percentage: ' + percentage + '%';
							}
						}
					}
				}
			}
		});
	},

	/**
	 * Create a line chart
	 */
	createLineChart: function(canvasId, title, data) {
		var ctx = document.getElementById(canvasId).getContext('2d');

		// Destroy existing chart if it exists
		if (page.chartInstances && page.chartInstances[canvasId]) {
			page.chartInstances[canvasId].destroy();
		}
		if (!page.chartInstances) {
			page.chartInstances = {};
		}

		var sortedKeys = Object.keys(data).sort();
		var rawMinutes = sortedKeys.map(function(key) {
			return data[key];
		});
		var values = rawMinutes.map(function(minutes) {
			return page.minutesToHours(minutes);
		});

		page.chartInstances[canvasId] = new Chart(ctx, {
			type: 'line',
			data: {
				labels: sortedKeys,
				datasets: [{
					label: 'Hours',
					data: values,
					borderColor: '#3498db',
					backgroundColor: 'rgba(52, 152, 219, 0.1)',
					borderWidth: 2,
					fill: true,
					tension: 0.4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				interaction: {
					mode: 'nearest',
					intersect: false
				},
				scales: {
					y: {
						beginAtZero: true
					}
				},
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						enabled: true,
						backgroundColor: 'rgba(0, 0, 0, 0.8)',
						padding: 12,
						titleFont: {
							size: 13,
							weight: 'bold'
						},
						bodyFont: {
							size: 12
						},
						callbacks: {
							title: function(context) {
								var date = new Date(context[0].label);
								return date.toLocaleDateString();
							},
							label: function(context) {
								var hours = context.parsed.y;
								var minutes = rawMinutes[context.dataIndex];
								return [
									'Hours: ' + hours,
									'Minutes: ' + minutes
								];
							}
						}
					}
				}
			}
		});
	},

	/**
	 * Create invoiced status chart
	 */
	createInvoicedChart: function(canvasId, title, data) {
		var ctx = document.getElementById(canvasId).getContext('2d');

		// Destroy existing chart if it exists
		if (page.chartInstances && page.chartInstances[canvasId]) {
			page.chartInstances[canvasId].destroy();
		}
		if (!page.chartInstances) {
			page.chartInstances = {};
		}

		var invoicedMinutes = data.invoiced;
		var notInvoicedMinutes = data.notInvoiced;
		var totalMinutes = invoicedMinutes + notInvoicedMinutes;

		page.chartInstances[canvasId] = new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: ['Invoiced', 'Not Invoiced'],
				datasets: [{
					data: [
						page.minutesToHours(invoicedMinutes),
						page.minutesToHours(notInvoicedMinutes)
					],
					backgroundColor: ['#2ecc71', '#e74c3c'],
					borderColor: ['#27ae60', '#c0392b'],
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				interaction: {
					mode: 'nearest',
					intersect: false
				},
				plugins: {
					legend: {
						position: 'bottom'
					},
					tooltip: {
						enabled: true,
						backgroundColor: 'rgba(0, 0, 0, 0.8)',
						padding: 12,
						titleFont: {
							size: 13,
							weight: 'bold'
						},
						bodyFont: {
							size: 12
						},
						callbacks: {
							label: function(context) {
								var label = context.label || '';
								var hours = context.parsed || 0;
								var minutes = context.dataIndex === 0 ? invoicedMinutes : notInvoicedMinutes;
								var percentage = totalMinutes > 0 ? ((minutes / totalMinutes) * 100).toFixed(1) : 0;

								return [
									label,
									'Hours: ' + hours,
									'Minutes: ' + minutes,
									'Percentage: ' + percentage + '%'
								];
							}
						}
					}
				}
			}
		});
	}

};


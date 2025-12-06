<?php
	$this->assign('title','TimeCase | Reports');
	$this->assign('nav','reports');

	$this->display('_Header.tpl.php');
?>

<script type="text/javascript">
	$LAB.script("bootstrap/js/bootstrap-datepicker.js")
	.script("bootstrap/js/bootstrap-combobox.js")
	.script("scripts/libs/underscore-min.js").wait()
	.script("scripts/libs/underscore.date.min.js")
	.script("scripts/libs/backbone.js")
	.script("scripts/app.js")
	.script("scripts/model.js").wait()
	.script("scripts/view.js").wait()
	.script("scripts/app/reports.js").wait()
	.script("scripts/timecase.js").wait()
	.script("chartjs/chart.js").wait()
	.script("scripts/app/reports.js").wait(function(){
		$(document).ready(function(){
			page.init();
		});

		// hack for IE9 which may respond inconsistently with document.ready
		setTimeout(function(){
			if (!page.isInitialized) page.init();
		},1000);
	});
</script>

<div class="container main">

<div class="row">
<div class="span12">

<h1>
	<i class="icon-file"></i> Reports
	<span id="modelLoader" class="loader progress progress-striped active"><span class="bar"></span></span>
	
	<span class='filter-button pull-right'>
	<button class="btn btn-primary showReportButton" data-type="csv"><i class="icon-download"></i>&nbsp; Download CSV</button>
	<button class="btn btn-primary showReportButton" data-type="html"><i class="icon-file"></i>&nbsp; Show HTML</button>
	</span>
</h1>
<div class="clearfix"></div>
	
</div>
</div> <!-- // row -->	

<div class="row">
<div class="span12">
	
<div id="filterContainer">
<div id="collectionAlert"></div>
<hr>

<form onsubmit="return false;" class="form">
<fieldset>
<div class="pull-left">
	<div class="control-group pull-left" id="filterStartInputContainer">
		<label for="start" class="control-label">From</label>
		<div class="controls ">
			<input type="text" value="<?php echo date('d-m-y')?>" id="start" class="date-picker input-large"> 
			<input type="text" class="time-picker input-xlarge" id="start-time" value="00:00">
			<span class="help-inline"></span>
		</div>
	</div>
	<div class="control-group pull-left" id="filterEndInputContainer">
		<label for="end" class="control-label">To</label>
		<div class="controls ">
			<input type="text" value="<?php echo date('d-m-y')?>" id="end" class="date-picker input-large">
			<input type="text" class="time-picker input-xlarge" id="end-time" value="23:59">
			<span class="help-inline"></span>
		</div>
	</div>
</div>

<div class="pull-right" id="dateRangePresets" style="margin-top: 8px;">
	<button type="button" class="btn btn-sm date-preset" data-preset="today">Today</button>
	<button type="button" class="btn btn-sm date-preset" data-preset="yesterday">Yesterday</button>
	<button type="button" class="btn btn-sm date-preset" data-preset="thisweek">This Week</button>
	<button type="button" class="btn btn-sm date-preset" data-preset="lastweek">Last Week</button>
	<button type="button" class="btn btn-sm date-preset" data-preset="thismonth">This Month</button>
	<button type="button" class="btn btn-sm date-preset" data-preset="lastmonth">Last Month</button>
</div>

<div class="clearfix"></div>

<div class="pull-left">

	<?php if(!($this->currentUser->LevelId & $this->ROLE_CUSTOMER)):?>
	<div class="control-group pull-left combo" id="filterCustomerIdInputContainer">
		<label for="customerId" class="control-label">Customer</label>
		<div class="controls">
			 <select name="customerId" id="customerId">
			 	<?php echo $this->cusomerOptions; ?>
			 </select>
			<span class="help-inline" style="display: none;"></span>
		</div>
	</div>
	<?php endif;?>
	
	<div class="control-group pull-left combo" id="filterProjectIdInputContainer">
		<label for="projectId" class="control-label">Project</label>
		<div id="parentProjectId" class="controls">
			<select id="projectId" name="projectId">
				<?php echo $this->projectOptions; ?>
			</select>
			<span class="help-inline" style="display: none;"></span>
		</div>
	</div>
	
	<div class="control-group pull-left combo" id="userIdInputContainer">
		<label for="userId" class="control-label">User</label>
		<div class="controls">
			 <select name="userId" id="userId">
			 	<?php echo $this->userOptions; ?>
			 </select>
			<span class="help-inline" style="display: none;"></span>
		</div>
	</div>
	
	<div class="control-group pull-left combo" id="filterCategoryIdInputContainer">
		<label for="categoryId" class="control-label">Work Type</label>
		<div class="controls">
			<select name="categoryId" id="categoryId">
				<?php echo $this->categoryOptions; ?>
			</select>
			<span class="help-inline" style="display: none;"></span>
		</div>
	</div>

	<div class="control-group pull-left combo" id="filterInvoicedInputContainer">
		<label for="invoicedFilter" class="control-label">Invoice Status</label>
		<div class="controls">
			<select name="invoicedFilter" id="invoicedFilter">
				<option value=""></option>
				<option value="0">Not Invoiced</option>
				<option value="1">Invoiced</option>
			</select>
			<span class="help-inline" style="display: none;"></span>
		</div>
	</div>

</div>
<div class="clearfix"></div>

</fieldset>
</form>

</div>

</div>

</div> <!-- // row -->


<div class="row">
<div class="span12">


<!-- underscore template for the collection -->
	<script type="text/template" id="timeEntryCollectionTemplate">
		<table class="collection table report">
		<thead>
			<tr>
				<th>Customer</th>
				<th id="header_ProjectId">Project<# if (page.orderBy == 'ProjectId') { #> <i class='icon-arrow-<#= page.orderDesc ? 'up' : 'down' #>' /><# } #></th>
				<th id="header_Att">Att</th>
				<th id="header_UserId">User<# if (page.orderBy == 'UserId') { #> <i class='icon-arrow-<#= page.orderDesc ? 'up' : 'down' #>' /><# } #></th>
				<th id="header_CategoryId">Work Type<# if (page.orderBy == 'CategoryId') { #> <i class='icon-arrow-<#= page.orderDesc ? 'up' : 'down' #>' /><# } #></th>
				<th id="header_Description">Description<# if (page.orderBy == 'Description') { #> <i class='icon-arrow-<#= page.orderDesc ? 'up' : 'down' #>' /><# } #></th>			
				<th id="header_Start">Start<# if (page.orderBy == 'Start') { #> <i class='icon-arrow-<#= page.orderDesc ? 'up' : 'down' #>' /><# } #></th>
				<th id="header_End">End<# if (page.orderBy == 'End') { #> <i class='icon-arrow-<#= page.orderDesc ? 'up' : 'down' #>' /><# } #></th>
				<th id="header_Duration">Duration</th>
				<th id="header_Invoiced">Invoiced</th>
			</tr>
		</thead>
		<tbody>
		<# items.each(function(item) { #>
			<tr id="<#= _.escape(item.get('id')) #>">
				<td><#= _.escape(item.get('customerName') || '') #></td>
				<td><#= _.escape(item.get('projectTitle') || '') #></td>
				<td><#= _.escape(item.get('att') || '') #></td>
				<td><#= _.escape(item.get('userName') || '') #></td>
				<td><#= _.escape(item.get('categoryName') || '') #></td>
				<td><#= _.escape(item.get('description') || '') #></td>
				<td><#if (item.get('start')) { #><#= _date(app.parseDate(item.get('start'))).format('MMM D, H:mm') #><# } else { #>NULL<# } #></td>
				<td><#if (item.get('end')) { #><#= _date(app.parseDate(item.get('end'))).format('MMM D, H:mm') #><# } else { #>NULL<# } #></td>
				<td class="rtext"><#= _.escape(item.get('durationFormatted') || '') #></td>
				<td><input type="checkbox" class="invoiced-checkbox" data-id="<#= _.escape(item.get('id')) #>" <# if (item.get('invoiced') == 1 || item.get('invoiced') == '1') { #>checked<# } #>></td>
			</tr>
		<# }); #>
		</tbody>
		</table>

<span id="report-total" class="label label-success pull-right">Total Duration: <span id="totalDurationHolder"></span></span>

		<#=  view.getPaginationHtml(page) #>
	</script>
	
	<div id="timeEntryCollectionContainer" class="collectionContainer"></div>


</div>
</div> <!-- /row -->

<!-- Charts Section -->
<div class="row">
<div class="span12">
	<hr>
	<h3>Analytics</h3>

	<div id="chartsContainer" style="display: none;">
		<div class="row">
			<div class="span6">
				<h4>Time per Customer</h4>
				<canvas id="timePerCustomerChart" height="100"></canvas>
			</div>
			<div class="span6">
				<h4>Time per User</h4>
				<canvas id="timePerUserChart" height="100"></canvas>
			</div>
		</div>

		<div class="row" style="margin-top: 30px;">
			<div class="span6">
				<h4>Time per Work Type</h4>
				<canvas id="timePerWorktypeChart" height="100"></canvas>
			</div>
			<div class="span6">
				<h4>Time per Week</h4>
				<canvas id="timePerWeekChart" height="100"></canvas>
			</div>
		</div>

		<div class="row" style="margin-top: 30px;">
			<div class="span6">
				<h4>Time per Project</h4>
				<canvas id="timePerProjectChart" height="100"></canvas>
			</div>
			<div class="span6">
				<h4>Invoiced vs Not Invoiced</h4>
				<canvas id="invoicedStatusChart" height="100"></canvas>
			</div>
		</div>
	</div>
	<div id="chartsEmptyMessage" style="text-align: center; color: #999; padding: 20px;">
		No data available for charts
	</div>
</div>
</div> <!-- /row -->

	<!-- footer -->
	<hr>

	<footer>

	</footer>

</div> <!-- /container -->

<?php
	$this->display('_Footer.tpl.php');
?>

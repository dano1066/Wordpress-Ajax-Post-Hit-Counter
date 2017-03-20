var lineChartData = {
	labels : dashwidget.labels,
	datasets : [
		{
			label : "Overview",
			backgroundColor: "#0073aa",
			data :  dashwidget.data 
		}
	]

}

window.onload = function(){
	var ctx = document.getElementById("hitcanvas").getContext("2d");
	window.myLine = new Chart(ctx, {
		type: 'bar',
		data: lineChartData,
		options: {
			elements: {
				rectangle: {
					borderWidth: 2,
					borderColor: 'rgb(0, 115, 170)',
					borderSkipped: 'bottom'
				}
			},
			responsive: true,
			legend: {
				display:false,
				position: 'top',
			},
			title: {
				display: false,
				text: 'Visitor Summary'
			}
		}
	});
}

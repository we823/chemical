seajs.config({
	plugins: ['shim'],
	alias: {
		'jquery': 'asset/jquery/2.2.4/jquery.cmd',
		'laytpl': 'asset/laytpl/laytpl',
		'echarts': 'asset/echarts/echarts.min',
		'bootstrap': 'asset/bootstrap/3.3.6/js/bootstrap.min'
	},
	preload: ['jquery'],
	map: [[/^(.*\.(?:css|js))(.*)$/i, '$1?v=20160819']]
});

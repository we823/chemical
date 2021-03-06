define(function(require, exports, module){
	var laytpl = require('laytpl');
    var echarts = require('echarts');
    require('bootstrap');
	
	exports.initCalculate = function(cal, nterm, cterm){
		
		//console.log('init');
		$('#calculate-button').on('click', function(){
			//console.log('click');
		    calculate($, laytpl, echarts);
	    });
	    
	    if(cal && cal==1){
			$('#CTerm').val(cterm);
			$('#NTerm').val(nterm);
			
			$('#calculate-button').click();
		}
	};
	
	exports.sCheck = function(){
       var $s2 = $('#s2');
       var $messageShow = $('#message-show');
       $s2.on('change', function(){
       	    $messageShow.addClass('hidden');
       	    var subject = $s2.val();
       	    if(subject.length==0){
       	    	return;
       	    }
		    var reg = /([1-9]*[0-9]+\-[1-9]*[0-9])/g;
		    var result = subject.match(reg);//reg.exec(subject); //subject.matches(reg);

		    var subjects = subject.split(',');
		    var newSubjects = new Array();
			for(index in subjects){
				if(subjects[index].length>0){
					newSubjects.push(subjects[index]);
				}
			}
			
			var resultLength = result==null ? 0 : result.length,
			    newSubjectsLength = newSubjects.length;
	
			if(resultLength < newSubjectsLength){
				$messageShow.removeClass('alert-info').removeClass('hidden').addClass('alert-danger').html('输入的二硫键修饰格式不对，请输入类似3-8,9-15');
			}
		
       });	
	};

});

function calculate($, laytpl){
	
	var $messageShow = $('#message-show'),
	    $resultShow = $('#result-show');
	$resultShow.addClass('hidden');
	    
	$messageShow.removeClass('alert-danger').addClass('alert-info').removeClass('hidden').html('正在计算中，请稍后...');
	$messageShow.show();
	var $cterm = $('#CTerm').val(),
	    $nterm = $('#NTerm').val(),
	    $amino = $('#amino').val();
	if(!$amino){
		$messageShow.removeClass('alert-info').addClass('alert-danger').html('请输入序列');
		$messageShow.removeClass('hidden');
		return;
	}
	var $form = $('#form-data');
	var url = $form.attr('action');
	
	$.post(url, $form.serialize(), function(result){
		if(result){
			if(result.hasError){
				$messageShow.removeClass('alert-info').addClass('alert-danger').html(result.message);
		        $messageShow.removeClass('hidden');
				return;
			}else{
				var tpl = document.getElementById('result_template').innerHTML;
				laytpl(tpl).render(result, function(html){
					$('#result-body').empty().html(html);

					var tableBodyTemplate = document.getElementById('table-body-template').innerHTML;
					
					var $tableBody = $('#table-body');
					$tableBody.empty();
                    
                    if(result.otherAmino){
                    	for(var amino in result.otherAmino){
						
							laytpl(tableBodyTemplate).render(result.otherAmino[amino], function(h){
								$tableBody.append(h);
							});
					    }
                    }
                    if(result.residue){
                    	for(var detail in result.residue.detail){
						
							laytpl(tableBodyTemplate).render(result.residue.detail[detail], function(h){
								$tableBody.append(h);
							});
						}
                    }

					$messageShow.addClass('hidden');
					$resultShow.removeClass('hidden');
					
					initEcharts(result, echarts);
				});
				
				
			}
		}
	});
}

function initEcharts(result, echarts){
	var lineChart = echarts.init(document.getElementById('line-echarts'));
	var ph=[];
	var data = result.y,
	    max = result.maxY,
	    min = -result.minY;
	for(var index=0; index<=14; index++){
		ph.push(index);
	}
	
	
	var option = {
	    title: {
	        text: '净电荷图('+ result['character3']+')'
	    },
	    smooth:true,
	    tooltip: {
	        trigger: 'axis',
	        formatter: function(params, ticket, callback){
	        	data = params[0].data;
	        	return 'pH:'+data[0]+'<br>'+params[0].seriesName+':'+data[1];
	        },
	        axisPointer: {
	            animation: false
	        }
	    },
	    toolbox: {
	        show: true,
	        feature: {
	            dataZoom: {
	                yAxisIndex: 'none'
	            },
	            dataView: {readOnly: false},
	            saveAsImage: {}
	        }
	    },
	    xAxis: {
	        type: 'value',
	        name: 'pH',
	        min:0,
	        max:14
	        //data: ph
	    },
	    yAxis: {
	        type: 'value',
	        name:'净电荷数',
	        min: min,
	        max: max,
	        axisLine: {
	        	onZero: false
	        },
	        splitLine: {
	            show: true,
	        }
	    },
	    series: [{
	        name: '净电荷',
	        type: 'line',
	        symbolSize:1,
	        showSymbol: true,
	        hoverAnimation: true,
	        data: data
	    }]
	};
    lineChart.setOption(option);
}
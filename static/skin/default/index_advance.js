define(function(require, exports, module){
	var laytpl = require('laytpl');
    var echarts = require('echarts');
    require('bootstrap');
	
	exports.initCalculate = function(cal, nterm, cterm){

		$('#calculate-button').on('click', function(){
		    calculate($, laytpl, echarts);
	    });
	    
	    if(cal && cal==1){
			$('#CTerm').val(cterm);
			$('#NTerm').val(nterm);
			
			$('#calculate-button').click();
		}
	    
	    $('#amino').on('change', function(){
	    	var $amino = $('#amino').val();
	    	if($amino.indexOf('chain')>-1){
	    		$('#cyclo-type-info').show();
	    		$('#cyclo-type').hide();
	    		$('#cyclo-type').val(-1);
	    	}else{
	    		$('#cyclo-type-info').hide();
	    		$('#cyclo-type-A').val(-1);
	    		$('#cyclo-type-B').val(-1);
	    		$('#cyclo-type').show();
	    	}
	    });
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
       	    subject = subject.toUpperCase();
		    var reg = /([A|B]?[1-9]*[0-9]+\-[A|B]?[1-9]*[0-9])/g;
		    var result = subject.match(reg);

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
				$messageShow.removeClass('alert-info').removeClass('hidden').addClass('alert-danger').html('输入的二硫键修饰格式不对，请输入类似3-8,9-15; 字母只能输入A或B');
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
	
	// amino与cyclo的选择关系
	var cycloType = $('#cyclo-type').val(),
	    cycloTypeA = $('#cyclo-type-A').val(),
	    cycloTypeB = $('#cyclo-type-B').val(),
	    cycloError = false;
	    cycloMessage = '';
	var tmp_type = -1;
	if(cycloType>-1 || cycloTypeA>-1 || cycloTypeB>-1){
		tmp_type = 0;
	}
	if(tmp_type>-1 && $amino.toLowerCase().indexOf('cyclo')<0){
		cycloError = true;
		cycloMessage = '选择了成环类型，但序列中不包含cyclo标记';
	}
	
	if(tmp_type==-1 && $amino.toLowerCase().indexOf('cyclo')>-1){
		cycloError = true;
		cycloMessage = '序列中包含cyclo标记, 请选择成环类型';
	}
	
	if($amino.indexOf('chain')>-1){
		var reg = /(cyclo)/gi;
		var result = $amino.match(reg);
		if(result){
			var len = result.length;
			if(cycloTypeA>-1 && cycloTypeB>-1){
				tmp_type = 2;
			}
			if(len>1){
				if(tmp_type!=2){
					cycloError = true;
		            cycloMessage = '序列中包含多个cyclo标记, 请分别选择成环类型';
				}
			}
		}
	}
	
	if(cycloError){
		$messageShow.removeClass('alert-info').addClass('alert-danger').html(cycloMessage);
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
                    if(result.aminoDetails){
                    	for(var detail in result.aminoDetails){
							laytpl(tableBodyTemplate).render(result.aminoDetails[detail], function(h){
								$tableBody.append(h);
							});
						}
                    }

                    var $pitableBody = $('#table-body-pi');
					$pitableBody.empty();
                    
                    laytpl(tableBodyTemplate).render({'name':'N-Term', 'count':result.ntermValue, 'residue':'-'}, function(h){
						$pitableBody.append(h);
					});
					laytpl(tableBodyTemplate).render({'name':'C-Term', 'count':result.ctermValue, 'residue':'-'}, function(h){
						$pitableBody.append(h);
					});
					
                    if(result.piAminos){
                    	for(var detail in result.piAminos){
							laytpl(tableBodyTemplate).render(result.piAminos[detail], function(h){
								$pitableBody.append(h);
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
	        text: '净电荷图('+ result['full']+')'
	    },
	    smooth:true,
	    tooltip: {
	        trigger: 'axis',
	        formatter: function(params, ticket, callback){
	        	data = params[0].data;
	        	if(data){
	        		return 'pH:'+data[0]+'<br>'+params[0].seriesName+':'+data[1];
	        	}else{
	        		return '数据不正常，无法显示';
	        	}
	        	
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
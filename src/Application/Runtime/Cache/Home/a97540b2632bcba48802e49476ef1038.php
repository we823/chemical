<?php if (!defined('THINK_PATH')) exit();?><!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
		<title>多肽计算器</title>
		<link charset="utf-8" rel="stylesheet" href="static/asset/bootstrap/3.3.6/css/bootstrap.min.css">
		
        <style>
        	.no-padding-right{
        		padding-right : 0!important;
        	}
        	.no-padding-left{
        		padding-left: 0!important;
        	}

        </style>
	</head>
	<body>
		<div class="container-fluid">
			<div class="row">
				<div class="jumbotron">
					<h4>多肽计算器</h4>
					<form action="index.php?m=home&c=index&a=result" method="post" class="form-horizontal" onsubmit="return false;">
					<div class="form-group">
						<label class="col-md-2 col-sm-2 col-xs-3 control-label">
							氨基端
						</label>
						<label class="col-md-6 col-sm-6 col-xs-6 control-label">
							序列（可输入氨基酸单字母或多字母，单字母为大写字母）
						</label>
						<label class="col-md-2 col-sm-2 col-xs-3 control-label">
							羧基端
						</label>
					</div>
					
					<div class="form-group">
						<div class="col-md-2 col-sm-2 col-xs-3 no-padding-right">
							<select name="NTerm" id="NTerm" class="form-control">
							<?php if(is_array($nterms)): foreach($nterms as $key=>$item): ?><option value="<?php echo ($item); ?>"><?php echo ($item); ?></option><?php endforeach; endif; ?>
						</select>
						</div>
						
						<div class="col-md-6 col-sm-6 col-xs-6 no-padding-left no-padding-right">
							<input type="text" name="amino" value="<?php echo ((isset($amino) && ($amino !== ""))?($amino):''); ?>" class="form-control" id="amino">
						</div>
						<div class="col-md-2 col-sm-2 col-xs-3 no-padding-left">
							<select name="CTerm" id="CTerm" class="form-control">
								<?php if(is_array($cterms)): foreach($cterms as $key=>$item): ?><option value="<?php echo ($item); ?>"><?php echo ($item); ?></option><?php endforeach; endif; ?>
							</select>
						</div>
						<div class="col-md-1  col-sm-2 col-xs-12">
							<button id="calculate-button" type="button" class="btn btn-primary pull-right"> 计算 </button>
						</div>
					</div>

				</form>
				<div id="message-show" class="alert alert-danger hidden" role="alert">警告内容</div>
				</div>
				<!--
                	/.col-md-12
                -->
			</div>
			<!-- /.row -->
			
			<div class="row">
				<div id="result-show" class="col-md-12 hidden">
					<div class="title"><h3>计算结果</h3></div>
					<div class="body" id="result-body"></div>
				</div>
			</div>
		</div>

		<script type="text/html" id="result_template">
			<div class="col-md-12">
			<ul class="list-unstyled">
				<li class="col-md-4 col-sm-6"><b>氨基酸个数：</b>{{d.residue.count}}</li>
				<li class="col-md-8 col-sm-6"><b>分子式：</b>{{d.molecularFomula}}</li>
				<li class="col-md-4 col-sm-12"><b>1字符：</b>{{d.character1}}</li>
				<li class="col-md-8 col-sm-12"><b>多字符：</b>{{d.character3}}</li>
				
				<li class="col-md-4 col-sm-6 hidden"><b>分子量：</b>{{d.residue.molecularWeight}}g/mol</li>
				<li class="col-md-4 col-sm-6"><b>平均分子量(MW)：</b>{{d.mw}}g/mol</li>
				<li class="col-md-8 col-sm-6"><b>精确分子量(Exact Mass)：</b>{{d.em}}</li>
				<li class="col-md-4 col-sm-6"><b>等电点(PI)：</b>{{d.isoelectricPoint}}</li>
				<li class="col-md-8 col-sm-6"><b>pH=7.0时的净电荷数：</b>{{d.pi7}}</li>
				<li class="col-md-4 col-sm-6"><b>平均亲水性：</b>{{d.hydrophilyResult}}</li>
				<li class="col-md-8 col-sm-12">
					<b>溶解性：</b>{{d.solubilityResult}} 
					<br><span style="color:#C0C0C0">( 备注：由于溶解性不仅与氨基酸的序列有关，也和产品所带的反离子有关，若溶解性遇到问题，可咨询我们的技术人员。)</span>
				</li>
			</ul>
			</div>
			<table class="table table-bordered">
				<tr>
					<th>
						氨基酸
					</th>
					<th>
						个数
					</th>
					<th>
					         残基分子量
					</th>
				</tr>
                <tbody id="table-body"></tbody>
                <tfoot>
                	<tr>
                		<td>氨基酸总数</td>
                		<td>{{d.residue.count}}</td>
                		<td></td>
                	</tr>
                	
                </tfoot>
			</table>
			<div class="row">
				<div id="line-echarts" style="height:500px" class="col-md-12"></div>
			</div>
			
		</script>
		<script id="table-body-template" type="text/html">
			<tr>
					<td>
						{{d.name3}}
					</td>
					<td>
						{{d.count}}
					</td>
					<td>
					   {{d.mw}}
					</td>
				</tr>
		</script>
			
		<script src="static/sea.js"></script>
		<script src="static/skin/default/config.js"></script>
		
		<script>
			seajs.use('skin/default/index', function(index){
				index.initCalculate(<?php echo ($cal); ?>, "<?php echo ((isset($nterm) && ($nterm !== ""))?($nterm):'H-'); ?>", "<?php echo ((isset($cterm) && ($cterm !== ""))?($cterm):'-OH'); ?>");
			})
		</script>
	</body>
</html>
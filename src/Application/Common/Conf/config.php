<?php
return array(
	//'配置项'=>'配置值'
	'simple_version'=>'0.0.1',
	'advance_version'=>'0.0.1',
	'base_index'=>array(
		'element_index'		=>array(
		   'C'=>'F',
		   'H'=>'G',
		   'N'=>'H',
		   'O'=>'I',
		   'S'=>'J',
		   'P'=>'K',
		   'Cl'=>'L'
		),
		'standard_index'	=>array(
		  'single'		=>'A',
		  'full'		=>'B',
		  'residue'		=>'E',
		  'solubility'	=>'M',
		  'hydrophily'	=>'N',
		  'ncterm'		=>'P',
		  'acid'		=>'Q',
		  'base'		=>'R',
		  'flag'		=>'S'
		),
		'pk_index'			=>array(
		   'single'	=>'A',
		   'full'	=>'B',
		   'pk'		=>'C',
		   'flag'	=>'D'
		),
		'const_index'=>array(
		   'name'	=>'A',
		   'mw'		=>'B',
		   'em'		=>'C'
		),
		'side_special_index'=>array(
		   'single'	=>'A',
		   'full'	=>'B',
		   'flag'	=>'C',
		   //1时有这个标记，需要继续计算。0则表示不需要继续计算
		   'memo_flag'=>'D',
		   'pre_link'=>'E' 
		)
	),
	'result_type'=>array(
		'cyclo_type'=>array(
		  0=>'main chain cyclo',
	      1=>'side chain cyclo',
	      2=>'main chain(N-term)&side chain cyclo',
	      3=>'main chain(C-term)&side chain cyclo'
		),
		'solubility_result'=>array(
		   0=>'可溶于水，但放置后成凝胶',
		   1=>'分子间易聚集，需要酸性缓冲液助溶',
		   2=>'分子间易聚集，需要碱性缓冲液助溶',
		   3=>'分子间易聚集，可能需要有机试剂助溶',
		   4=>'水溶',
		   5=>'水溶，但放置后可能会成多聚体',
		   6=>'水可溶，但放置后可能会成多聚体',
		   7=>'水可溶',
		   8=>'需要碱性缓冲液助溶',
		   9=>'需要甲酸或DMSO助溶',
		   10=>'需要碱性缓冲液和有机试剂助溶',
		   11=>'需要甲酸或DMSO助溶',
		   12=>'难溶'
		),
		'hydrophily_result'=>array(
		   0=>'非常亲水',
		   1=>'亲水',
		   2=>'疏水',
		   3=>'非常疏水'
		)
	),
	'default_value'=>array(
	  'nterm'=>'H-',
	  'cterm'=>'OH'
	)
);
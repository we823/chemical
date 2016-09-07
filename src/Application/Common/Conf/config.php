<?php
return array(
	//'配置项'=>'配置值'
	'DB_TYPE' => 'mysql',
	'DB_HOST' => '120.24.87.82',
    'DB_USER' => 'root',
    'DB_PWD' => 'ZhaowenYuYu2015',
    'DB_NAME' => 'we823_chemical',
    'DB_CHARSET' => 'utf8',
    'DB_PORT' => 3306,
    'autoconnect' => 1,
    'webname' => '多肽氨基酸计算',
    'base'=>__ROOT__,
    'nterm_flag'=>'2,4',
    'cterm_flag'=>'3,4',
	'simple_version'=>'0.0.1',
	'advance_version'=>'0.0.1',
	'element_index'=>array(
		 'C','H','N','O','S','P','Cl','Se'
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
		   10=>'需要碱性缓冲液或有机试剂助溶',
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
	  'cterm'=>'OH',
	  'Ac'=>'Ac',
	  'NH2'=>'NH2',
	  'lys_single'=>'K',
	  'glu_single'=>'E'
	)
);
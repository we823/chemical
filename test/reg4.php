<?php
	
	$subject = 'Cyclo(ABC)cyclo(cyclo(EF))cyclo(EF)cyclo(EF)cyclo(EF)';
	$pattern = '/cyclo/i';
	
	$result = preg_match_all($pattern, $subject, $items);
	
	var_dump($result);
	var_dump($items);

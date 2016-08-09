<?php
	
	namespace Common\Model;
	use Think\Model;
	
	class AminoChainModel extends Model{
		private $original;
		private $hasCyclo=false;
		private $preCyclo;
		private $cyclo;
		private $afterCyclo;
		private $startIndex = -1;
		private $endIndex = -1;
		
	}

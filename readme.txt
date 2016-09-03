1 excel表格数据:
     简单版：data\data_simple.xls
     专业版：data\data_full.xls 已转到数据库中

2 数据根据现有表的格式填写，standard中为校验及氨基酸基本信息
  （a） single 填写单字母，如内容与其他氨基酸有冲突的，nterm填写如n-，nterm填写如-c
  （b） full 填写多字母
  （c） 若不存在单字母或多字母，保证single和full中都需要填写
  （d） flag列填写数字，
       1表示为普通氨基酸，
       2为nterm，
       3为cterm,
       4为无N-term且需要增加1个Lys，
       5为需要增加1个C-term,
       6为需要增加一个pS，在C端则C-term为0，在Glu或Asp侧链则Glu或Asp减1。
  (e) term_value: 表明当此氨基酸对应flag=2、flag=3时term的值
  (f) cyclo_enable: 
       -1： 酸性氨基酸，可参与成环
       0： 无法成环
       1: 碱性氨基酸，可参与成环
       2： 硫醚成环及二硫键成对
  (g) solubility: 溶解性
  (h) hydrophily: 亲水性

3 pi的表中flag标记0为公式1,1为公式2
     ratio: 氨基酸个数的系数，默认为1

4 side_special表
  flag=1，表示需要计算氨基酸个数
  memo_flag: 用于备注信息的识别。1：括号内肯定不是备注 0：括号内可能为备注，需要进一步判断
  pre_link: 氨基酸分析后的结果，2个片段之间连接的符号，0：空格 1：-
  pi: 作为特殊标记对pi值的影响，非0，则需要加上
  term: 标记括号内需要如何计算term，0为忽略或2者都计算，1只计算nterm，-1只计算cterm
  
5 系统中增加新的元素，步骤
  (a) 在amino_standard中增加字段
  (b) 在amino_const中增加记录
  (c) 在application\common\conf\config.php 中找到element_index中增加元素字段名称
  
  
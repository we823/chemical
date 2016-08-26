1 excel表格数据:
     简单版：data\data_simple.xls
     专业版：data\data_full.xls

2 数据根据现有表的格式填写，standard中为校验及氨基酸基本信息
  （a） A 填写单字母，nterm填写如n-，nterm填写如-c
  （b） B 填写多字母
  （c） 若不存在单字母或多字母，保证A和B中都需要填写
  （d） R列填写数字，1表示为序列需要校验，2为nterm，3为cterm,4为无N-term且需要增加1个Lys，5为需要增加1个C-term,
        6为需要增加一个Ps，在C端则C-term为0，在Glu或Asp侧链则Glu或Asp减1。

3 pk的表中flag标记0为公式1,1为公式2

4 side_special表
  flag=1，表示需要计算氨基酸个数
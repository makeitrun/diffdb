<?php

class DbDiffer
{
	public $dbStruct1 = NULL;
	public $dbStruct2 = NULL;
	public $dbCreate1 = NULL;
	public $dbCreate2 = NULL;
	public $dbIndex1 = NULL;
	public $dbIndex2 = NULL;
	
	public $diffData = NULL;
	
	public static $SUPPORT = array('Type', 'Null', 'Default', 'Key');

	function __construct($dbFile1, $dbFile2)
	{
		$ret = $this->readFile( $dbFile1 );
		$this->dbStruct1 = $ret['struct'];
		$this->dbCreate1 = $ret['create'];
		$this->dbIndex1 = $ret['index'];
		
		$ret = $this->readFile( $dbFile2 );
		$this->dbStruct2 = $ret['struct'];
		$this->dbCreate2 = $ret['create'];
		$this->dbIndex2 = $ret['index'];
	}
	
	public function readFile($filePath)
	{
		$ret = file_get_contents( $filePath );
		if( empty($ret) )
		{
			MyLog::fatal('read file:%s failed', $filePath );
			throw new Exception("read file failed");
		}
		$ret = unserialize( $ret );
		if( empty( $ret ) )
		{
			MyLog::fatal('unserialize file:%s fialed', $filePath);
			throw new Exception("unserialize file failed");
		}
		return $ret;
	}
	
	public function diffTable($table1, $table2)
	{
		$t1 = arrayIndex($table1, 'Field');
		$t2 = arrayIndex($table2, 'Field');

		$fieldNameList1 = array_keys( $t1 );
		$fieldNameList2 = array_keys( $t2 );
		
		$only1 = array_diff( $fieldNameList1, $fieldNameList2 );
		$only2 = array_diff( $fieldNameList2, $fieldNameList1 );
		$sameList = array_diff( $fieldNameList1, $only1 );
		
		$diffFieldList = array();
		foreach($sameList as $fieldName)
		{
			$field1 = $t1[$fieldName];
			$field2 = $t2[$fieldName];
			$diff = array();			
			foreach( $field1 as $k => $v )
			{
				if( $field1[$k] != $field2[$k] )
				{
					$diff[$k] = array(1 =>$field1[$k], 2 =>$field2[$k]);
				}
			}
			if(!empty($diff))
			{
				$diffFieldList[$fieldName] = $diff;
			}
		}
		
		$returnData = array();
		if(!empty($only1))
		{
			$returnData['only1'] = $only1;
		}
		if(!empty($only2))
		{
			$returnData['only2'] = $only2;
		}
		if(!empty($diffFieldList))
		{
			$returnData['diff'] = $diffFieldList;
		}
		return $returnData;
	}

	public function diffIndex($table1, $table2)
	{
		$indexList1 = array_keys( $table1 );
		$indexList2 = array_keys( $table2 );
	
		$only1 = array_diff( $indexList1, $indexList2 );
		$only2 = array_diff( $indexList2, $indexList1 );
		$sameList = array_diff( $indexList1, $only1 );
	
		$diffIndexList = array();
		foreach($sameList as $indexName)
		{
			$index1 = $table1[$indexName];
			$index2 = $table2[$indexName];
			$diff = array();
			foreach( $index1 as $k => $v )
			{
				if( $index1[$k]['field'] != $index2[$k]['field'] || 
						$index1[$k]['Non_unique'] != $index2[$k]['Non_unique'] )
				{
					$diff[$k] = array(1 =>$index1[$k], 2 =>$index2[$k]);
				}
			}
			if(!empty($diff))
			{
				$diffIndexList[$indexName] = $diff;
			}
		}
	
		$returnData = array();
		if(!empty($only1))
		{
			$returnData['only1'] = array();
			foreach ($only1 as $key)
			{
				$returnData['only1'][] = $table1[$key]['Key_name'];
			}
		}
		if(!empty($only2))
		{
			$returnData['only2'] = array();
			foreach ($only2 as $key)
			{
				$returnData['only2'][] = $table2[$key]['Key_name'];
			}
		}
		if(!empty($diffIndexList))
		{
			$returnData['diff'] = $diffIndexList;
		}
		return $returnData;
	}
	
	public function getDiff()
	{
		if( !empty($this->diffData) )
		{
			return $this->diffData;
		}
		$tableList1 = array_keys( $this->dbStruct1 );
		$tableList2 = array_keys( $this->dbStruct2 );
		
		$only1 = array_diff( $tableList1, $tableList2 );
		$only2 = array_diff( $tableList2, $tableList1 );
		$sameList = array_diff( $tableList1, $only1 );
	
		$diffTaleList = array();
		foreach($sameList as $tableName)
		{
			$diff = $this->diffTable( $this->dbStruct1[$tableName], $this->dbStruct2[$tableName]  );
			if( !empty($diff) )
			{
				$diffTaleList[$tableName]['field'] = $diff;
			}		
			$diff = $this->diffIndex( $this->dbIndex1[$tableName], $this->dbIndex2[$tableName]  );
			if( !empty($diff) )
			{
				$diffTaleList[$tableName]['index'] = $diff;
			}	
		}
		
		$returnData = array(
			'only1' => $only1,
			'only2' => $only2,
			'diff' => $diffTaleList,
		);
		
		$this->diffData = $returnData;
		return $returnData;
	}
	
	public function genUpdateSql()
	{
		if(empty($this->dbCreate2))
		{
			MyLog::fatal('no create table sql');
			return 'erro';
		}
		
		$ret = $this->getDiff();
		$only1 = $ret['only1'];
		$only2 = $ret['only2'];
		$diffTableList = $ret['diff'];
		
		MyLog::info('gen update sql for db1');
		
		//建库的
		$sql = "set names utf8;\n\n";
		foreach ( $only2 as $tableName)
		{			
			$createSql = $this->dbCreate2[$tableName];
			$createSql = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $createSql);
			
			$sql .= $createSql.";\n\n";
		}
				
		foreach($diffTableList as $tableName => $tableInfo)
		{
			if( !empty($tableInfo['field']))
			{
				$table = $tableInfo['field'];
				//多字段
				if(!empty($table['only1']))
				{
					MyLog::fatal('more field in db1. table:%s, %s', $tableName, $table['only1']);
					$sql .= sprintf("-- more field in db1, table:%s fields:%s\n\n", $tableName, implode(',', $table['only1']));
				}
				
				//少字段的
				if(!empty($table['only2']))
				{
					foreach( $table['only2'] as $fieldName )
					{
						$createSql = $this->dbCreate2[$tableName];
						$fieldSql = self::searchLine($createSql, "/^\s*`$fieldName`/");
						if(count($fieldSql) != 1)
						{
							MyLog::fatal('erro in table:%s, field:%s, found:%s', $tableName, $fieldName, $fieldSql);
							return 'erro';
						}
						$fieldSql = array_shift($fieldSql);
						$fieldSql = trim($fieldSql, ',');
						$alterSql = "ALTER TABLE $tableName ADD COLUMN $fieldSql";
						
						$lastField = self::getLastField($createSql, $fieldSql);
						if( !empty($lastField)  )
						{
							$alterSql = "$alterSql AFTER $lastField";
						}
							
						$sql .= "-- add field\n";
						$sql .= $alterSql.";\n\n";
					}
				}
				
				//字段有差异的
				if(isset($table['diff']))
				{				
					foreach($table['diff'] as $fieldName => $value)
					{								
						$diffList = array_keys($value);		
						$notSupport = array_diff($diffList, self::$SUPPORT);
						if( !empty($notSupport) )
						{
							MyLog::fatal('table:%s field:%s. not support field diff:%s ', $tableName, $fieldName, $notSupport);
							$sql .= sprintf("-- not support diff. table:%s field:%s, diff:%s\n", 
										$tableName, $fieldName, implode(',', $notSupport));
							continue;
						}
						
						$createSql = $this->dbCreate2[$tableName];
						$fieldSql = self::searchLine($createSql, "/^\s*`$fieldName`/");
						if(count($fieldSql) != 1)
						{						
							MyLog::fatal('erro in table:%s, field:%s, found:%s', $tableName, $fieldName, $fieldSql);
							return 'erro';
						}
						$fieldSql = array_shift($fieldSql);
						$fieldSql = trim($fieldSql, ',');
						$alterSql = "ALTER TABLE $tableName CHANGE $fieldName $fieldSql";
						
						$sql .= sprintf("-- change field:%s \n", implode(',', $diffList));
						$sql .= $alterSql.";\n\n";
					}
				}
			
			}


			//对比索引
			if( !empty($tableInfo['index']))
			{
				$table = $tableInfo['index'];
				if(!empty($table['only1']))
				{
					foreach( $table['only1'] as $indexName )
					{
						if(  $indexName == 'PRIMARY' )
						{
							$alterSql = "ALTER TABLE $tableName DROP PRIMARY KEY";
						}
						else
						{
							$alterSql = "ALTER TABLE $tableName DROP INDEX $indexName";
						}
							
						$sql .= sprintf("-- drop index: \n");
						$sql .= "-- ".$alterSql.";\n\n";
					}
					
				}
				if(!empty($table['only2']))
				{
					foreach( $table['only2'] as $indexName )
					{
						$createSql = $this->dbCreate2[$tableName];
						if( $indexName == 'PRIMARY' )
						{					
							$fieldSql = self::searchLine($createSql, "/PRIMARY KEY/");
						}
						else
						{
							$fieldSql = self::searchLine($createSql, "/KEY[\s`\(]*`$indexName`/");
						}
						
						if(count($fieldSql) != 1)
						{
							MyLog::fatal('erro in table:%s, index:%s, found:%s', $tableName, $indexName, $fieldSql);
							return 'erro';
						}
						$fieldSql = array_shift($fieldSql);
						$fieldSql = trim($fieldSql, ',');
						$alterSql = "ALTER TABLE $tableName ADD $fieldSql";
							
						$sql .= sprintf("-- add index: \n");
						$sql .= "-- ".$alterSql.";\n\n";
					}
				}
				
				if(isset($table['diff']))
				{
					foreach( $table['diff'] as $indexName => $diffInfo )
					{
						$createSql = $this->dbCreate2[$tableName];
						if( $indexName == 'PRIMARY' )
						{					
							$fieldSql = self::searchLine($createSql, "/PRIMARY KEY/");
							$dropSql = "ALTER TABLE $tableName DROP PRIMARY KEY";
						}
						else
						{
							$fieldSql = self::searchLine($createSql, "/KEY[\s`\(]*`$indexName`/");
							$dropSql = "ALTER TABLE $tableName DROP INDEX $indexName;";
						}
						if(count($fieldSql) != 1)
						{
							MyLog::fatal('erro in table:%s, index:%s, found:%s', $tableName, $indexName, $fieldSql);
							return 'erro';
						}
						$fieldSql = array_shift($fieldSql);
						$fieldSql = trim($fieldSql, ',');
						
						$sql .= sprintf("-- change index: \n");
						$sql .= "-- $dropSql;\n";
						$sql .= "-- ALTER TABLE $tableName ADD $fieldSql;\n\n";
					}
				}
			}
		}
		
		return $sql;
	}
	
	public static function searchLine($str, $patten)
	{
		$lines = explode("\n", $str);
		$returnData = array();
		foreach($lines as $line)
		{
			if(preg_match($patten , $line ))
			{
				$returnData[] = $line;
			}
		}
		return $returnData;
	}
	
	public static function getLastField($str, $curLine)
	{
		$lastLine = '';
		$curLine = "$curLine,";
		$lines = explode("\n", $str);
		foreach($lines  as $line)
		{
			if( $line == $curLine )
			{
				break;
			}
			$lastLine = $line;
		}
		
		$lastField = '';
		if(preg_match("/^\s*`([a-zA-z0-9\_]+)`/" , $lastLine, $matches))
		{
			$lastField = $matches[1];
		}
		Mylog::debug('lastLine:%s, lastField:%s', $lastLine, $lastField);
		return $lastField;
	}
	
	
	public function genDiffInfo()
	{
		$ret = $this->getDiff();
		$only1 = $ret['only1'];
		$only2 = $ret['only2'];
		$diffTableList = $ret['diff'];
		
		$msg = '';
		
		$msg .= sprintf("there are [%d] table only in db1:\n%s\n", count($only1), implode("\n",$only1));
		$msg .= "====================================================\n";
		$msg .= sprintf("there are [%d] table only in db2:\n%s\n", count($only2), implode("\n",$only2));
		$msg .= "====================================================\n";
		
		foreach($diffTableList as $tableName => $tableInfo)
		{			
			if( !empty($tableInfo['field']))
			{
				$table = $tableInfo['field'];
				
				$msg .= sprintf("\n[%s]\n", $tableName);
				if(!empty($table['only1']))
				{
					$msg .= sprintf("\tfield only in db1:\n\t\t%s\n", implode(', ', $table['only1']) );
				}
				if(!empty($table['only2']))
				{
					$msg .= sprintf("\tfield only in db2:\n\t\t%s\n", implode(', ', $table['only2']) );
				}
				
				if(isset($table['diff']))
				{
					$msg .= sprintf("\tfield diff:\n");
					foreach($table['diff'] as $fieldName => $value)
					{					
						foreach($value as $k => $v)
						{
							$msg .= sprintf("\t\t[%s]: %s { %s vs %s }\n", $fieldName, $k, $v[1], $v[2]);
						}
					}
				}
				
				$msg .= "\n";
			}
			
			//对比索引
			if( !empty($tableInfo['index']))
			{
				$table = $tableInfo['index'];
				if(!empty($table['only1']))
				{
					$msg .= sprintf("\tindex only in db1:\n\t\t%s\n", implode(', ', $table['only1']) );
				}
				if(!empty($table['only2']))
				{
					$msg .= sprintf("\tindex only in db2:\n\t\t%s\n", implode(', ', $table['only2']) );
				}
					
				if(isset($table['diff']))
				{
					$msg .= sprintf("\tindex diff:\n");
					foreach($table['diff'] as $fieldName => $value)
					{
						foreach($value as $k => $v)
						{
							$msg .= sprintf("\t\t[%s]: %s { %s vs %s }\n", $fieldName, $k, $v[1], $v[2]);
						}
					}
				}
			}
		}
		
		return $msg;
	}
	
}

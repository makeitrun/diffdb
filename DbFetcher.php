<?php
class DbFetcher
{
	public $host;
	public $db;
	public $uname;
	public $passwd;
	
	public $dbCon;
	
	function __construct($host, $db, $uname, $passwd)
	{
		$this->host = $host;
		$this->db = $db;
		$this->uname = $uname;
		$this->passwd = $passwd;
		
		$this->connect();
	}
	
	public function connect()
	{
		$this->dbCon = mysqli_connect( $this->host, $this->uname, $this->passwd, $this->db);
		mysqli_query($this->dbCon, "set names utf8;");
	}
	
	public function query( $sql)
	{
		$query = mysqli_query($this->dbCon, $sql);
		$error = mysqli_error($this->dbCon);

		if ( !empty($error) )
		{
			MyLog::fatal('db erro. sql:%d, err:%s', $sql, $error);
			return array();
		}

		if ( strpos($sql, 'INSERT') !== 0 and
			strpos($sql, 'insert') !== 0 and
			strpos($sql, 'UPDATE') !== 0 and
			strpos($sql, 'update') !== 0 )
		{
			$return = array();
			while ( TRUE )
			{
				$row = mysqli_fetch_array($query, MYSQLI_ASSOC);
				if ( empty($row) )
				{
					break;
				}
				//deal va
				foreach ( $row as $key => $value )
				{
					if ( strpos($key, "va_") === 0 )
					{
						$row[$key] = Util::amfDecode($value);
					}
				}
				$return[] = $row;
			}
			return $return;
		}
	}
	
	public function getAllTableInfo($needCreate = false)
	{
		$ret = $this->query( "show tables");	
		$tableList = array();
		foreach ( $ret as $value )
		{
			$tableList[] = array_shift($value );
		}
		
		$structInfo = array();
		foreach($tableList as $tableName)
		{
			$ret = $this->query( "desc $tableName");	
			foreach($ret as $k => $v)
			{
				unset($ret[$k]['Key']);
			}
			$structInfo[ $tableName ] = $ret;
		}
		
		$indexInfo = array();
		foreach($tableList as $tableName)
		{
			$ret = $this->query( "show index from $tableName");
		
			$info = array();
			foreach($ret as $value)
			{
				$info[ $value['Key_name'] ]['field'][ $value['Seq_in_index'] ] = $value['Column_name'];
				$info[ $value['Key_name'] ]['Non_unique'] = $value['Non_unique'];
				$info[ $value['Key_name'] ]['Key_name'] = $value['Key_name'];
			}
			MyLog::debug('before: %s, %s', $tableName, $info);
			foreach($info as $k => $value)
			{
				ksort($value['field']);
				$indexName = implode(',', $value['field']);				
				$info[ $k ]['field'] = $indexName;
				
				if( $k != 'PRIMARY' &&  $indexName != $k )
				{
					if( isset($info[ $indexName ]) )
					{
						MyLog::fatal("Duplicate key $indexName");
						throw new Exception("Duplicate key $indexName");
					}
					$info[ $indexName ] = $info[ $k ];
					unset($info[ $k ]);
				}
			}
			MyLog::debug('after:%s', $info);
			
			$indexInfo[ $tableName ] = $info;
		}
		
		$createInfo = array();
		if($needCreate)
		{
			foreach($tableList as $tableName)
			{
				$ret = $this->query( "show create table $tableName");	
				$ret = array_shift($ret);
				$createInfo[$tableName ] = $ret['Create Table'];
			}					
		}
		$allInfo = array(
				'struct' => $structInfo,
				'create' => $createInfo,
				'index' => $indexInfo
				);
		return $allInfo;
	}
}

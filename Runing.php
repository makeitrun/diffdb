<?php
MyLog::init('log_diff_db');
if( count($argv) < 2)
{
	help();
	return;
}
$op = $argv[1];

$uname = 'pirate';
$passwd = 'admin';


switch($op)
{
	case 'help':
		help();
		break;
	case 'get':
		if( count($argv) < 5)
		{
			help();
			break;
		}
		$needCreateSql = 0;
		if(isset($argv[5]))
		{
			$needCreateSql = $argv[5];
		}
		
		getDbInfo($argv[2], $argv[3], $uname, $passwd, $argv[4], $needCreateSql);
		break;
	case 'diff':
		if( count($argv) < 4)
		{
			help();
			break;
		}		
		diffDb($argv[2], $argv[3], 'update_'.date ( 'Ymd' ).'.sql');
		break;
	case 'import':
		if( count($argv) < 5)
		{
			help();
			break;
		}
		importDb($argv[2], $argv[3], $uname, $passwd, $argv[4]);
		break;
	
	case 'all':
		if( count($argv) < 5)
		{
			help();
			break;
		}
		$host = $argv[2];
		$test_db = $argv[3];
		$line_sql_file = $argv[4];
		$tmp_db = 'tmp_for_update';
		
		$file_test = $test_db.".sql";
		$file_tmp= $tmp_db.".sql";
		
		importDb($host, $tmp_db, $uname, $passwd, $line_sql_file);
		
		getDbInfo($host, $test_db, $uname, $passwd, $file_test, 0);
		
		getDbInfo($host, $tmp_db, $uname, $passwd, $file_tmp, 1);
		
		diffDb($file_test, $file_tmp, 'update_'.date ( 'Ymd' ).'.sql');
		break;
	default:
		MyLog::fatal('invalid op:%s', $op);
}

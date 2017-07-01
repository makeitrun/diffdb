<?php


function help()
{
	echo "all host db line_sql_file  根据官网线上数据库结构的sql文件，直接生成差异sql\n";
	echo "\t host:测试服数据库的ip\n";
	echo "\t db:测试服数据库的名字\n";
	echo "\t line_sql_file:官网线上数据库结构的sql文件\n";
	echo "\t 执行此命令会在测试服数据库中创建一个临时db tmp_for_update \n\n\n";
	
	echo "import host tmpDbName line_sql_file  将官网线上数据库结构的sql文件导入到测试服数据库中\n";
	echo "\t tmpDbName:临时db名字\n\n\n";

	echo "get host db file [neecCreateSql(0/1)]\n";
	echo "\t db:需要导出结构的db名字（可能是测试服db，或者上面的临时db\n";
	echo "\t file:导出的文件名 \n";
	echo "\t neecCreateSql:是否需要导出建表sql（导出临时db时需要置为1） \n\n\n";
	
	echo "diff file1 file2\n";
	echo "\t file1:测试服db结构文件\n";
	echo "\t file2:临时db结构文件\n\n";
	
}

function getDbInfo($host, $db, $uname, $passwd, $file, $needCreateSql)
{
	MyLog::info('get db info. host:%s, db:%s, file:%s, neecCreateSql:%d', $host, $db, $file, $needCreateSql);
	
	$dbFetcher = new DbFetcher($host, $db, $uname, $passwd);
	$dbInfo = $dbFetcher->getAllTableInfo($needCreateSql);
	
	file_put_contents($file, serialize($dbInfo));
}

function diffDb($file1, $file2, $outputFile)
{
	MyLog::info('get db info. file1:%s, file2:%s', $file1, $file2);

	$dbDiffer = new DbDiffer($file1, $file2);
		
	$ret = $dbDiffer->genDiffInfo();

	MyLog::debug("db diff:\n%s", $ret);
	
	$ret = $dbDiffer->genUpdateSql();
	
	file_put_contents($outputFile, $ret);
	
	MyLog::info("result in file:%s", $outputFile);
	
}

function importDb( $host, $db, $uname, $passwd, $file)
{
	$cmd = sprintf("sed '1 idrop database if exists %s; create database %s; use %s;' -i %s", $db, $db, $db, $file);

	system($cmd);	
	
	$sql = sprintf('mysql -u%s -p%s -h%s < %s', $uname, $passwd, $host, $file);
	MyLog::info('import db:%s', $sql);
	$ret = system($sql);
	MyLog::info('import db result:%s', $ret);
}

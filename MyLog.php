
<?php

class MyLog
{
    private static $fid;

    public static function init($filename)
    {
        self::$fid = fopen($filename, 'a');
    }
    private static function log($level, $arrArg, $print = 0)
    {
        $arrMicro = explode ( " ", microtime () );
        $content = '[' . date ( 'Ymd H:i:s ' );
        $content .= sprintf ( "%06d", intval ( 1000000 * $arrMicro [0] ) );
		$content .= '][';
		$content .= $level;
		$content .= "]";
		
        foreach ( $arrArg as $idx => $arg )
        {
            if ($arg instanceof BtstoreElement)
            {
                $arg = $arg->toArray ();
            }
            if (is_array ( $arg ))
            {
                $arrArg [$idx] = var_export ( $arg, true );
            }
        }
        $content .= call_user_func_array ( 'sprintf', $arrArg );
        $content .= "\n";

        if($print)
        {
            echo $content;
        }
        fprintf(self::$fid, $content);

    }
    public static function debug()
    {
        $arrArg = func_get_args ();
        self::log('DEBUG', $arrArg, false);
    }
    public static function info()
    {
        $arrArg = func_get_args ();
        self::log('INFO', $arrArg, true);
    }
    public static function fatal()
    {
        $arrArg = func_get_args ();
        self::log('FATAL', $arrArg, true);
    }
}

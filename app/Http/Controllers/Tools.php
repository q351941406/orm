<?php
namespace App\Http\Controllers;


class Tools {
    public static  function getMemFree(){
        $contents = file_get_contents('/proc/meminfo');
        preg_match_all('/(\w+):\s+(\d+)\s/', $contents, $matches);
        $info = array_combine($matches[1], $matches[2]);
        return $info['MemFree']/1024/1204;
    }
}






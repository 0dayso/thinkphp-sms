<?php
/*=============================================================================
#     FileName: sort_black_dict.php
#         Desc: 和谐字段排序/去重
#       Author: 荒野无灯
#      Version: 0.0.1
#   LastChange: 2013-12-05 19:26:23
#      History:
=============================================================================*/

if(PHP_SAPI != 'cli') {
    die('Access denied.');
}
$file = dirname(__FILE__) . '/black_dict.csv';
$file_arr = file($file);
$sorted   = array();
$cnt      = count($file_arr);

function merge_sort(&$arr){
    $i = 0;
    $left_min = 0;
    $left_max = 0;
    $right_min = 0;
    $right_max = 0;
    $next      = 0;
    $tmp       = array();
    $length    = count($arr);
    for ($i = 1; $i < $length; $i *= 2)
        for ($left_min = 0; $left_min < $length - $i; $left_min = $right_max) {
            $right_min = $left_max = $left_min + $i;
            $right_max = $left_max + $i;
            if ($right_max > $length)
                $right_max = $length;
            $next = 0;
            while ($left_min < $left_max && $right_min < $right_max)
                $tmp[$next++] = mb_strlen($arr[$left_min]) > mb_strlen($arr[$right_min]) ? $arr[$right_min++] : $arr[$left_min++];
            while ($left_min < $left_max)
                $arr[--$right_min] = $arr[--$left_max];
            while ($next > 0)
                $arr[--$right_min] = $tmp[--$next];
        }
    unset($tmp);
}

sort($file_arr);
merge_sort($file_arr);
$sorted_arr = $file_arr;

foreach($sorted_arr as $item) {
    $item = trim($item);
    $sorted[$item] = TRUE;
}

$sorted_no_dup = array_keys($sorted);

$fp            = fopen('black_dict_sorted.csv', 'ab');
foreach($sorted_no_dup as $s) {
    fwrite($fp, $s . "\n");
}
fclose($fp);
echo "Done.\n";

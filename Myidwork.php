<?php

/**
 * ID 生成策略
 * 毫秒级时间41位+机器ID 10位+毫秒内序列12位。
 * 0           41     51     64
 *
 * 0---0000000000 0000000000 0000000000 0000000000 0 --- 00000 ---00000 ---0000000000 00
 *      41                                                  5       5           12
 *
 +-----------+----------+---------+-------+
 |time       |dataid    |workid   | incr  |
 +-----------+----------+---------+-------+
 *  前41bits是以毫秒为单位的timestamp。
 *  5bits是datacenter标识位。
 *  5bits是机器ID
 *  最后12bits是累加计数器。
 *
 * @author hanxiaojun
 */
class Myidwork
{

    const debug = 1;

    static $workerId;

    static $datacenterId;

    //static $twepoch = 1361775855078;
    static $twepoch = 1470823773089;

    static $sequence = 0;
    //定义进程号
    static $mypid;

    // 数据中心标识位数
    const datacenterIdBits = 5;
    // 机器标识位数
    const workerIdBits = 5;
    // 生成ID标示位
    const sequenceBits = 12;
    // 机器ID最大值 算法：-1 ^ (-1 << self::workerIdBits)
    static $maxWorkerId = 31;
    // 数据中心ID最大值 算法：-1 ^ (-1 << self::datacenterIdBits)
    static $maxDatacenterId = 31;
    // 机器ID偏左移12位
    static $workerIdShift = self::sequenceBits;
    // 数据中心ID左移17位 算法：sequenceBits + workerIdBits
    static $datacenterIdShift = 17;
    // 时间毫秒左移22位 算法：sequenceBits + workerIdBits + datacenterIdBits
    static $timestampLeftShift = 22;
    // 每毫秒产生的id数量 1023 算法：-1 ^ (-1 << self::sequenceBits)
    static $sequenceMask = 1023;

    private static $lastTimestamp = - 1;

    function __construct($workId)
    {
        // 获取进程号
        self::$mypid = getmypid();

        if ($workId > self::$maxWorkerId || $workId < 0) {
            throw new Exception("worker Id can't be greater than 15 or less than 0");
        }
        self::$workerId = $workId;
        self::$datacenterId = self::$mypid % self::$maxDatacenterId;
    }

    function timeGen()
    {
        // 获得当前时间戳
        $time = explode(' ', microtime());
        $time2 = substr($time[0], 2, 3);
        return $time[1] . $time2;
    }

    function tilNextMillis($lastTimestamp)
    {
        $timestamp = $this->timeGen();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->timeGen();
        }
        return $timestamp;
    }

    function nextId()
    {
        $timestamp = $this->timeGen();

        if (self::$lastTimestamp == $timestamp) {
            self::$sequence = (self::$sequence + 1) & self::$sequenceMask;
            if (self::$sequence == 0) {
                $timestamp = $this->tilNextMillis(self::$lastTimestamp);
            }
        } else {
            self::$sequence = 0;
        }
        if ($timestamp < self::$lastTimestamp) {
            throw new Exception("Clock moved backwards.  Refusing to generate id for " . (self::$lastTimestamp - $timestamp) . " milliseconds");
        }
        self::$lastTimestamp = $timestamp;

        // ID偏移组合生成最终的ID，并返回ID
        // ((timestamp - twepoch) << timestampLeftShift) | (datacenterId << datacenterIdShift) | (workerId << workerIdShift) | sequence

        $nextId = ((sprintf('%.0f', $timestamp) - sprintf('%.0f', self::$twepoch)) << self::$timestampLeftShift) | (self::$datacenterId << self::$datacenterIdShift) | (self::$workerId << self::$workerIdShift) | self::$sequence;
        return $nextId;
    }
}

$Myidwork = new Myidwork(2);
$nextId = $Myidwork->nextId();
$str = $Myidwork::$mypid."-".$nextId."\n";
$handle = fopen('/tmp/Myidwork.txt', "a");
if (flock($handle, LOCK_EX)) { // 进行排它型锁定
    fwrite($handle, $str);
    flock($handle, LOCK_UN); // 释放锁定
}
fclose($handle);

/*
for ($i = 0; $i <= 1000; $i ++) {
    echo 'nextId->' . $nextId;
    echo '<br />';
}
*/

?>

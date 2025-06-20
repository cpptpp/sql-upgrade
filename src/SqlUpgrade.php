<?php

namespace Meioa\Tools;


use Exception;
use RuntimeException;

/**
 * 执行指定目录下所有文件内的sql
 */
class SqlUpgrade
{


    private $_sqlLock = 'sql.lock';
    private $_dbConfig;

    private $_sqlFileSuffix = ['sql', 'txt', 'csv', 'log'];

    private $_highRiskKeywords = ['drop', 'truncate', 'delete'];

    private $_enableHighRisk = false;
    /**
     * @var Db
     */
    private $_db;


    /**
     * 设置sql文件扩展名
     * @param string[] $suffix
     * @return $this
     */
    public function setSqlFileSuffix($suffix) {
        $this->_sqlFileSuffix = $suffix;
        return $this;
    }

    /**
     * 设置sql 高风险关键词
     * @param string[] $keywords
     * @return $this
     */
    public function setHighRiskKeywords( $keywords){
        $this->_highRiskKeywords = $keywords;
        return $this;
    }

    /**
     * 设置开启、关闭 高风险sql检测
     * @param bool $status
     * @return $this
     */
    public function setHighRisk($status = false){
        $this->_enableHighRisk = $status;
        return $this;
    }
    /**
     * @param $dbConfig array 数据库配置数组
     */
    public function __construct($dbConfig)
    {
        $this->_dbConfig = $dbConfig;
    }

    /**
     * @param $sqlFile string sql文件名
     * @return string
     */
    private function _getSqlFromFile($sqlFile){
        $content = file_get_contents($sqlFile);
        // 移除多行注释 /* ... */
        $sql = preg_replace('/\/\*.*?\*\//s', '', $content);
        // 移除单行注释 -- ... 保留换行 原格式不变
        $sql = preg_replace('/\s*--.*(?=\n)/', '', $sql);
        // 移除多余的空白行
        $sql = preg_replace('/([\r\n])\s*/', "", $sql);
        return trim($sql);
    }

    /**
     * @param $sqlDir string sql 目录
     * @return array|false
     */
    private function _getSqlFileList($sqlDir){

        // 将扩展名数组转换为适合 glob() 的格式
        $pattern = $sqlDir . '/*.{'. implode(',', $this->_sqlFileSuffix) .'}';
        // 使用 glob() 获取所有非 .lock 文件
        return glob($pattern, GLOB_BRACE);
    }

    private function _checkHighRiskSql($sql) {


        $pattern = '/\b(' . implode('|', $this->_highRiskKeywords) . ')\b/i';
        // 检查 SQL 语句中是否包含高风险关键字
        if (preg_match($pattern, $sql)) {
            return true;
        }
        return false;
    }

    /**
     * @param $sqlFile  string sql文件名
     * @return array|false
     */
    private function _execFileSql($sqlFile){

        $sql = $this->_getSqlFromFile($sqlFile);
        //检测执行风险
        if($this->_enableHighRisk && $this->_checkHighRiskSql($sql)){
            throw new RuntimeException(basename($sqlFile).' : HIGH RISK SQL!');
        }
        $sqlList = explode(';',$sql);
        if(empty($sqlList)){
            return false;
        }
//        $tips = '开始执行文件:'.basename($sqlFile);
//        echo $tips.PHP_EOL;
        $sqlListRes = [];
        foreach ($sqlList as $sqlLine){
            if(empty($sqlLine)){
                continue;
            }
            //var_dump($sqlLine);
            try {
                $dbRes = $this->_db->exec($sqlLine);
                array_push($sqlListRes,[$sqlLine,$dbRes]);
            } catch(Exception $e){
                throw new RuntimeException('SQL :'.$sqlLine.',ERROR :'.$e->getMessage());
            }
        }
        return $sqlListRes;
    }

    /**
     * @param $sqlDir  string sql目录
     * @return array
     */
    public function run($sqlDir){

        $dbConfig = $this->_dbConfig;

        if(!is_dir($sqlDir) || !is_readable($sqlDir)){
            throw new DirException(sprintf(' sql目录 "%s" 不存在,或不能读取的目录', $sqlDir));
        }
        $this->_db = new Db($dbConfig);
        //$this->_db->exec("use ".$dbConfig['database']);
//        var_dump($res);die;
        $sqlFileList = $this->_getSqlFileList($sqlDir);
        ksort($sqlFileList);

        //var_dump($sqlFileList);

        $sqlLock = $sqlDir.'/'.$this->_sqlLock;

        $sqlRes = [];
        $sqlUpgradeArr = [];
        $upgradeFileList = [];
        if(is_file($sqlLock)){

            $sqlUpgradeArr = json_decode(file_get_contents($sqlLock),true);
            if(is_array($sqlUpgradeArr) && count($sqlUpgradeArr)>0){
                foreach ($sqlUpgradeArr as $filename => $sqlUpgrade){
                    if($sqlUpgrade==1){
                        array_push($upgradeFileList,$filename);
                    }
                }
            }
        }

        foreach ($sqlFileList as $sqlFile){
            $sqlFilename = basename($sqlFile);
            if(in_array($sqlFilename,$upgradeFileList)){
                continue;
            }
            //var_dump($sqlFilename);
            $sqlRes[$sqlFilename] = $this->_execFileSql($sqlFile);
            $sqlUpgradeArr[$sqlFilename] = 1;
            //var_dump($sqlList);
        }

        //保存执行结果到目录下的sql.lock
        ksort($sqlUpgradeArr);
        file_put_contents($sqlLock,json_encode($sqlUpgradeArr));
        return $sqlRes;
    }
}

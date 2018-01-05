<?php
/**
 * Created by chatfeed.
 * @Author:$Id$
 */
namespace cfd\doc\components;

use yii\base\UserException;
use yii\db\ActiveRecord;

/**
 * Class DocObject
 * @package cfd\doc\components
 */
class DocObject{
    private $objects=[];
    private $relectionMap=[];
    private $modelsMap=[];
    public function __construct($objects)
    {
        $this->objects = $objects;
        $this->modelsMap = \Yii::$app->getModule('doc')->modelsMap;
    }

    public function getObject($name,$attributes=[])
    {
        if(strpos($name,'@')!==false){
            list($name,$attr_str) = explode("@",$name);
            $attributes = explode(",",$attr_str);
        }
        if(isset($this->objects[$name])){
            if(empty($attributes)){
                return $this->objects[$name];
            }else{
                $ret = [];
                foreach($this->objects[$name] as $idx=>$item){
                    if($idx==0){
                        $ret[] = $item;
                        continue;
                    }else{
                        if(in_array($item[1],$attributes)){
                            $ret[] = $item;
                        }
                    }
                }
                return $ret;
            }
        }

        $classname = $this->findClassName($name);
        $relection = new \ReflectionClass($classname);
        /** @var ActiveRecord $model */
        $model = new $classname;
        $commonTypes = ['integer','int','float','double','string','array'];
        if (is_subclass_of($model, 'yii\db\ActiveRecord')) {
            $ret[] = [
                'object',$name,''
            ];
            $labels = $model->attributeLabels();
            if(empty($attributes)){
                $attributes = array_keys($labels);
            }
            $classComment = $relection->getDocComment();
//            preg_match_all("/property (.*) \\$(.*) (.*?)/i",$classComment,$matches);
            preg_match_all("/property (.*)/i",$classComment,$ms);
            $matches =[];
            foreach($ms[0] as $m){
                $arr = explode(' ',$m);
                $arr = array_map('trim',$arr);
                $arr[2] = substr($arr[2],1);
                $matches[$arr[2]] = $arr;
            }

            foreach($attributes as $a){
                if(isset($matches[$a])){
                    $type = $matches[$a][1];
                    if(!in_array($type,$commonTypes)){
                        $cname = $this->findClassName($type);
                        if(class_exists($cname)){
                            $type = "object";
                        }
                    }
                    if(isset($matches[$a][3])){
                        $label = $matches[$a][3];
                    }elseif(isset($labels[$a])){
                        $label = $labels[$a];
                    }else{
                        var_dump("{$classname} {$a} label 不存在");exit;
                        throw new UserException("{$classname} {$a} label 不存在");
                    }
                    $ret[] = [
                        $type,
                        $a,
                        $label
                    ];
                }else{
                    var_dump("{$classname} 的 {$a} 文档注释不存在");exit;
                    throw new UserException("{$classname} 的 {$a} 文档注释不存在");
                }
            }
            return $ret;
        }else{
            throw new UserException("{$classname} 不存在");
        }
    }

    public function getRelection($classname){
        if(isset($this->relectionMap[$classname])){
//            return $this->rel
        }
    }
    /**
     * @param $name
     * @return ActiveRecord
     */
    public function findClassName($name){
        if(strpos($name,"\\")===0){
            //完整全名空间
            return $name;
        }else {
            foreach ($this->modelsMap as $namespace){
                $class = $namespace . ucfirst($name);
                //找到类就返回，没有返回最后一个
                if (class_exists($class)) {
                    break;
                }
            }
            return $class;
        }
    }


    /**
     * Generates parameter tags for phpdoc
     * @return array parameter tags for phpdoc
     */
    public function generateActionParamComments()
    {
        /* @var $class ActiveRecord */
        $class = $this->modelClass;
        $pks = $class::primaryKey();
        if (($table = $this->getTableSchema()) === false) {
            $params = [];
            foreach ($pks as $pk) {
                $params[] = '@param ' . (substr(strtolower($pk), -2) == 'id' ? 'integer' : 'string') . ' $' . $pk;
            }

            return $params;
        }
        if (count($pks) === 1) {
            return ['@param ' . $table->columns[$pks[0]]->phpType . ' $id'];
        } else {
            $params = [];
            foreach ($pks as $pk) {
                $params[] = '@param ' . $table->columns[$pk]->phpType . ' $' . $pk;
            }

            return $params;
        }
    }

    /**
     * Returns table schema for current model class or false if it is not an active record
     * @param ActiveRecord $class
     * @return boolean|\yii\db\TableSchema
     */
    public function getTableSchema($class)
    {
        if (is_subclass_of($class, 'yii\db\ActiveRecord')) {
            return $class::getTableSchema();
        } else {
            return false;
        }
    }
    /**
     * @param ActiveRecord $class
     * @return array model column names
     */
    protected function getColumnNames($class)
    {
        if (is_subclass_of($class, 'yii\db\ActiveRecord')) {
            return $class::getTableSchema()->getColumnNames();
        } else {
            /* @var $model \yii\base\Model */
            $model = new $class();
            return $model->attributes();
        }
    }
}
<?php
namespace cfd\doc\components;
/**
 * Created by PhpStorm.
 * User: chatfeed
 * Date: 2018/1/5
 * Time: 下午2:01
 */
use Yii;
use yii\caching\TagDependency;
use yii\helpers\VarDumper;

class Route extends \yii\base\Object
{
    const CACHE_TAG = 'cfd.doc.routes';
    public $cacheDuration = 3600;
    public $isCache = false;

    public $model_data = [];
    public $model_name = '';

    /**
     * Get avaliable and assigned routes
     * @return array
     */
    public function getRoutes()
    {
        return $this->getAppRoutes();

    }

    /**
     * Get list of application routes
     * @return array
     */
    public function getAppRoutes($module = null)
    {
        if ($module === null) {
            $module = Yii::$app;
        } elseif (is_string($module)) {
            $module = Yii::$app->getModule($module);
        }
        $key = [__METHOD__, $module->getUniqueId()];
        $cache = Yii::$app->cache;
        if (false || $cache === null || !$this->isCache || ($result = $cache->get($key)) === false) {
            $result = [];

            $this->getRouteRecrusive($module, $result);

            if ($cache !== null && $this->isCache) {
                $cache->set($key, $result, $this->cacheDuration, new TagDependency([
                    'tags' => self::CACHE_TAG,
                ]));
            }
        }

        return $result;
    }

    /**
     * Get route(s) recrusive
     * @param \yii\base\Module $module
     * @param array $result
     */
    protected function getRouteRecrusive($module, &$result)
    {
        $token = "Get Route of '" . get_class($module) . "' with id '" . $module->uniqueId . "'";
        Yii::beginProfile($token, __METHOD__);
        try {
            foreach ($module->getModules() as $id => $child) {
                if(in_array($id,['debug','gii'])) continue;
                if (($child = $module->getModule($id)) !== null) {
                    $this->getRouteRecrusive($child, $result);
                }
            }

            foreach ($module->controllerMap as $id => $type) {
                $this->getControllerActions($type, $id, $module, $result);
            }

            $namespace = trim($module->controllerNamespace, '\\') . '\\';
            $this->getControllerFiles($module, $namespace, '', $result);
            $all = '/' . ltrim($module->uniqueId . '/*', '/');
            $result[$all] = $all;

        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), __METHOD__);
        }

        Yii::endProfile($token, __METHOD__);

    }

    /**
     * Get list controller under module
     * @param \yii\base\Module $module
     * @param string $namespace
     * @param string $prefix
     * @param mixed $result
     * @return mixed
     */
    protected function getControllerFiles($module, $namespace, $prefix, &$result)
    {
        $path = Yii::getAlias('@' . str_replace('\\', '/', $namespace), false);
        $token = "Get controllers from '$path'";
        Yii::beginProfile($token, __METHOD__);
        try {
            if (!is_dir($path)) {
                return;
            }
            foreach (scandir($path) as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                if (is_dir($path . '/' . $file) && preg_match('%^[a-z0-9_/]+$%i', $file . '/')) {
                    $this->getControllerFiles($module, $namespace . $file . '\\', $prefix . $file . '/', $result);
                } elseif (strcmp(substr($file, -14), 'Controller.php') === 0) {
                    $baseName = substr(basename($file), 0, -14);
                    ##@todo 更新正则表达式
                    // $name = strtolower(preg_replace('/(?<![A-Z])[A-Z]/', ' \0', $baseName));
                    // var_dump($id = ltrim(str_replace(' ', '-', $name), '-'));
                    $name = strtolower(preg_replace('/([A-Z])/', ' \0', $baseName));
                    $id   = str_replace(' ', '-', ltrim($name));
                    $className = $namespace . $baseName . 'Controller';
                    if(in_array($baseName,['Doc'])) continue;
                    if (strpos($className, '-') === false && class_exists($className) && is_subclass_of($className, 'yii\base\Controller')) {
                        $this->getControllerActions($className, $prefix . $id, $module, $result);
                    }
                }
            }
        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), __METHOD__);
        }
        Yii::endProfile($token, __METHOD__);
    }

    /**
     * Get list action of controller
     * @param mixed $type
     * @param string $id
     * @param \yii\base\Module $module
     * @param string $result
     */
    protected function getControllerActions($type, $id, $module, &$result)
    {
        $token = "Create controller with cofig=" . VarDumper::dumpAsString($type) . " and id='$id'";
        Yii::beginProfile($token, __METHOD__);
        try {
            if($module->getUniqueId()=='doc' && $id!='demo'){
                return ;
            }
            /* @var $controller \yii\base\Controller */
            $controller = Yii::createObject($type, [$id, $module]);
            $this->getActionRoutes($controller, $result);
            $all = "/{$controller->uniqueId}/*";
            $class = new \ReflectionClass($controller);
            $docComment = $class->getDocComment();
            $docCommentArr = explode("\n", $docComment);
            $result[$all] = ['name'=>$all];
            foreach ($docCommentArr as $comment) {
                $comment = trim($comment);
                //@desc注释
                $pos = stripos($comment, '@desc');
                if ($pos !== false) {
                    $result[$all]['name'] = substr($comment, $pos + 5);
                    continue;
                }
            }
        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), __METHOD__);
        }
        Yii::endProfile($token, __METHOD__);
    }

    /**
     * Get route of action
     * @param \yii\base\Controller $controller
     * @param array $result all controller action.
     */
    protected function getActionRoutes($controller, &$result)
    {
        $description = '';
        $descComment = '//请使用@desc 注释';
        $typeMaps = array(
            'string' => '字符串',
            'int' => '整型',
            'float' => '浮点型',
            'boolean' => '布尔型',
            'date' => '日期',
            'array' => '数组',
            'fixed' => '固定值',
            'enum' => '枚举类型',
            'object' => '对象',
        );
        $token = "Get actions of controller '" . $controller->uniqueId . "'";
        Yii::beginProfile($token, __METHOD__);
        try {
            $prefix = '/' . $controller->uniqueId . '/';
            foreach ($controller->actions() as $id => $value) {
                //$result[$prefix . $id] = $prefix . $id;
            }
            $class = new \ReflectionClass($controller);

            // var_dump(get_class_methods($controller));exit;
            // var_dump($class->getMethods());exit;
            foreach ($class->getMethods() as $key11 => $method) {

                $name = $method->getName();
                if(strpos($name, 'format') === 0) {
                    //生成对象列表
                    $docComment = $method->getDocComment();
                    $docCommentArr = explode("\n", $docComment);
                    $object_name = '';

                    foreach ($docCommentArr as $comment) {
                        //@return注释
                        // if($pos = stripos($comment, '@doc-return') !== false){
                        if(stripos($comment, '@doc-return') !== false){
                            $pos = stripos($comment, '@doc-return');
                            $returnCommentArr = explode(' ', substr($comment, $pos + 11));
                            //将数组中的空值过滤掉，同时将需要展示的值返回
                            $returnCommentArr = array_values(array_filter($returnCommentArr));
                            if (count($returnCommentArr) < 2) {
                                continue;
                            }
                            if (!isset($returnCommentArr[2])) {
                                $returnCommentArr[2] = '';    //可选的字段说明
                            } else {
                                //兼容处理有空格的注释
                                $returnCommentArr[2] = implode(' ', array_slice($returnCommentArr, 2));
                            }
                            $nameArr = explode('.',$returnCommentArr['1']);

                            $cnt = count($nameArr);
                            if($cnt>1){
                                $returnCommentArr[1] = $nameArr[1];
                            }
                            $result['objectlist'][$nameArr[0]][] = $returnCommentArr;

                        }else if(stripos($comment, '@return') !== false){

                            $pos = stripos($comment, '@return');
                            $returnCommentArr = explode(' ', substr($comment, $pos + 7));
                            $returnCommentArr = array_values(array_filter($returnCommentArr));

                            if (!isset($returnCommentArr[1])) {
                                $returnCommentArr[1] = '';    //可选的字段说明
                            } else {
                                //兼容处理有空格的注释
                                $returnCommentArr[1] = implode(' ', array_slice($returnCommentArr, 1));
                            }

                            if(preg_match("/^\w*(=>)?\w*$/", $returnCommentArr[0],$match)){

                                $object = explode('=>', $match[0]);

                                $object_name = isset($object[1])?$object[1]:$object[0];
                                $result['objectlist'][$object_name][] = ['object',$object_name,$returnCommentArr[1]];
                                $this->set_model_name($object[0]);
                                continue;
                            }
                            if(preg_match("/^\((.*)\)$/", $returnCommentArr[0],$match)){
                                $this->set_model_data($match[1],'need');
                                continue;
                            }
                            if(preg_match("/^\[(.*)\]$/", $returnCommentArr[0],$match)){
                                $this->set_model_data($match[1],'needless');
                                continue;
                            }
                            if(preg_match("/^\{(.*)\}$/", $returnCommentArr[0],$match)){
                                $this->set_model_data($match[1],'extra');
                                continue;
                            }
                        }
                    }
                    if($object_name){
                        $return_data = $this->get_model_data();
                        $this->set_model_data($object_name,'','');

                        foreach ($return_data as $key => $row) {
                            $result['objectlist'][$object_name][] = $row;
                        }
                    }

                    // var_dump($result['objectlist']);exit;
                    continue;
                }
                if ($method->isPublic() && !$method->isStatic() && strpos($name, 'action') === 0 && $name !== 'actions') {
                    ##@todo 更新正则表达式
                    // $name = strtolower(preg_replace('/(?<![A-Z])[A-Z]/', ' \0', substr($name, 6)));
                    $name = strtolower(preg_replace('/[A-Z]/', ' \0', substr($name, 6)));
                    $id = $prefix . ltrim(str_replace(' ', '-', $name), '-');
                    if(!empty($method->getParameters())){
                        //特殊处理带ID的方法
                        $id.='/:id';
                    }
                    //$result[$id] = $id;
                    $result[$id] = [
                        'id' => $id,
                        'description' => '',
                        'descComment' => '//请使用@desc 注释',
                        'request' => [],
                        'response' => [],
                    ];
                    $docComment = $method->getDocComment();
                    $docCommentArr = explode("\n", $docComment);
                    foreach ($docCommentArr as $comment) {
                        $comment = trim($comment);

                        //标题描述
                        if (empty($result[$id]['description']) && strpos($comment, '@') === false && strpos($comment, '/') === false) {
                            $result[$id]['description'] = (string)substr($comment, strpos($comment, '*') + 1);
                            continue;
                        }

                        //@desc注释
                        $pos = stripos($comment, '@desc');
                        if ($pos !== false) {
                            $result[$id]['descComment'] = substr($comment, $pos + 5);
                            continue;
                        }

                        //@param注释
                        $pos = stripos($comment, '@param');
                        if ($pos !== false) {
                            $params = [
                                'name' => '',
                                'type' => '',
                                'require' => true,
                                'default' => '',
                                'other' => '',
                                'desc' => ''
                            ];
                            $paramCommentArr = explode(' ', substr($comment, $pos + 7));
                            if (preg_match('/\$[A-Z0-9]*/', @$paramCommentArr[1])) {
                                $params['name'] = substr($paramCommentArr[1], 1);
                                $params['type'] = $paramCommentArr[0];
                                foreach ($paramCommentArr as $k => $v) {
                                    if ($k < 2) {
                                        continue;
                                    }
                                    $params['desc'] .= $v;
                                }
                                if($params['desc'] && strpos($params['desc'],'可选')!=false){
                                    $params['require']=false;
                                }
                                foreach ($method->getParameters() as $item) {
                                    if ($item->getName() !== $params['name']) {
                                        continue;
                                    }
                                    $params['require'] = !$item->isDefaultValueAvailable();
                                    if (!$params['require']) {
                                        $params['default'] = $item->getDefaultValue();
                                    }
                                }
                            }
                            $result[$id]['request'][] = $params;
                            continue;
                        }
                        //@param注释
                        $pos = stripos($comment, '@version');
                        if ($pos !== false) {
                            $versionArr = explode(' ', substr($comment, $pos + 9));
                            $result[$id]['version'] = (int)$versionArr[0];
                        }

                        //@return注释
                        $pos = stripos($comment, '@doc-return');
                        if ($pos === false) {
                            continue;
                        }

                        $returnCommentArr = explode(' ', substr($comment, $pos + 12));
                        //将数组中的空值过滤掉，同时将需要展示的值返回
                        $returnCommentArr = array_values(array_filter($returnCommentArr));
                        if (count($returnCommentArr) < 2) {
                            continue;
                        }
                        if (!isset($returnCommentArr[2])) {
                            $returnCommentArr[2] = '';    //可选的字段说明
                        } else {
                            //兼容处理有空格的注释
                            $returnCommentArr[2] = implode(' ', array_slice($returnCommentArr, 2));
                        }

                        $result[$id]['response'][] = $returnCommentArr;
                    }


                }
            }
        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), __METHOD__);
        }
        Yii::endProfile($token, __METHOD__);
    }

    private function set_model_name($name){

        $this->model_name = $name;

        $className = '\common\models\\'.$this->model_name;

        if(class_exists($className)){
            $model = new $className();
        }else{
            $this->model_data[] = '';
            return false;
        }

        $attributes = $model->attributes;

        $attributes_key = array_keys($attributes);

        foreach ($attributes_key as $key => $row) {

            $db_type = $model::getTableSchema($this->model_name)->columns[$row]->dbType;
            $db_type_common = (isset($model->attributeLabels()[$row]))?$model->attributeLabels()[$row]:$model::getTableSchema()->columns[$row]->comment;
            $this->model_data[$row] = [$db_type,$row,$db_type_common];
        }

    }

    private function set_model_data($attribute="",$type='extra'){

        $className = '\common\models\\'.$this->model_name;

        if(class_exists($className)){
            $model = new $className();
        }else{
            $this->model_data[] = '';
            return false;
        }

        $attributes = $model->attributes;

        $attributes_key = array_keys($attributes);

        switch ((string)$type) {
            case 'need':
                //处理需要的属性 eg：id,name
                $attribute_need = explode(',', $attribute);

                foreach ($attributes_key as $key => $row) {

                    if(!in_array($row, $attribute_need)){

                        unset($this->model_data[$row]);

                    }
                }

                break;
            case 'needless':
                //处理不需要的属性eg：mobile,zone
                $attribute_needless = explode(',', $attribute);

                foreach ($attribute_needless as $key => $row) {

                    if(isset($this->model_data[$row])){

                        unset($this->model_data[$row]);

                    }
                }

                break;
            case 'extra':
                //处理额外需要的属性eg：['int','abc','额外的参数'],['string','ac','外的参数']

                if(preg_match_all("/(\[.*?\])/", $attribute,$match)){

                    if($match){
                        foreach ($match[1] as $key => $value) {
                            $row = str_replace("'", '', $value);
                            $row = preg_replace("/[\[\]]/", '', $row);
                            // $length = mb_strlen($row);
                            // $row = mb_substr($row, 1,$length - 2);
                            $extra_array = explode(',', $row);
                            $this->model_data[$extra_array[1]] = $extra_array;
                        }
                    }
                }

                break;
            default:
                $this->model_data = [];
                break;
        }
    }

    private function get_model_data(){
        return $this->model_data;
    }
}

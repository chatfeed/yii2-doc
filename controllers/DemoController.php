<?php
namespace cfd\doc\controllers;
/**
 * Created by PhpStorm.
 * User: chatfeed
 * Date: 2018/1/5
 * Time: 下午2:19
 */

class DemoController extends \yii\web\Controller{

    /**
     * 示例接口
     * @param integer $id ID
     * @param string $type 类型
     * @version 1
     * @doc-return integer num 整数
     * @doc-return object<demo> demo1 自定义模型
     * @doc-return string str1 字符串
     * @doc-return object<demo@id,name> demo2 自定义模型可选字段
     * @doc-return object<teacher> tec 自有数据模型
     * @doc-return object<teacher@cardId,is_checked> tec 自有数据模型选用字段
     */
    public function actionGet(){

    }
}
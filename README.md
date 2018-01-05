# yii2-doc
======================
auto generate api document by using comment for yii2.

Installation
======================

```
composer require chatfeed/yii2-doc "*"
```

Configure
======================

```php
    'module'=>[
        'doc' => [
            'class' => 'cfd\doc\Module',
            'modelDescriptions'=>require __DIR__.'/model_description.php',
            'modelsMap'=>[
                '\common\base_models\kds\\',
            ]
        ]
    ]
```

Define Customer Model
======================
```php
return [
    'demo'=>[
        ['object','demo','模型'],
        ['integer','id','ID'],
        ['string','name','名称'],
        ['string','desc','描述'],
        ['integer','created_at','创建时间'],
        ['integer','updated_at','更新时间'],
    ]
];
```
Screenshot
======================
![screenshots](https://github.com/chatfeed/yii2-doc/blob/master/screenshot.jpg)
Thanks [yii2-fast-api](https://github.com/deepziyu/yii2-fast-api)

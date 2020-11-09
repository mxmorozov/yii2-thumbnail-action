Controller action for thumbnail generation and caching
======================================================
This extension purpose is to make thumbnails of images on the fly.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist mxmorozov/yii2-thumbnail-action "*"
```

or add

```
"mxmorozov/yii2-thumbnail-action": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :

```php
<?= \mxmorozov\thumbnail\AutoloadExample::widget(); ?>```
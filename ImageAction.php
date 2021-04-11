<?php
/**
 * Created by PhpStorm.
 * User: Maksim Morozov <maxpower656@gmail.com>
 * Date: 09.11.2020
 * Time: 10:55
 */

namespace mxmorozov\thumbnail;

use Closure;
use Imagine\Image\Box;
use Yii;
use yii\base\Action;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use yii\web\NotFoundHttpException;

class ImageAction extends Action
{
    const RATIO_1X = '1x';
    const RATIO_2X = '2x';
    const RATIO_3X = '3x';

    const RATIOS = [self::RATIO_1X => 1, self::RATIO_2X => 2, self::RATIO_3X => 3];

    public array $sizes;
    public Closure $getOriginImageFileName;
    public Closure $getImageBaseName;
    public string $cachePath;
    public string $modelClass;

    private string $_sizeName;
    private int $_size;
    private ?string $_ratio = null;
    private ActiveRecord $_model;
    private string $_originImageFileName;
    private string $_targetImagePath;
    private string $_targetImageFileName;


    public function init()
    {
        $this->_model = $this->modelClass::findOne((int)Yii::$app->request->get('id'));

        if (!($this->_model instanceof ActiveRecord)) {
            throw new NotFoundHttpException();
        }

        $this->_sizeName = (string)Yii::$app->request->get('size');
        if (array_key_exists($this->_sizeName, $this->sizes)) {
            $this->_size = $this->sizes[$this->_sizeName];
        } else {
            throw new NotFoundHttpException();
        }

        if ($this->_ratio = (string)Yii::$app->request->get('ratio'))
        {
            if (!array_key_exists($this->_ratio, self::RATIOS)) {
                throw new NotFoundHttpException();
            }
            $this->_targetImagePath = $this->cachePath . DIRECTORY_SEPARATOR . $this->_model::tableName() . DIRECTORY_SEPARATOR . $this->_sizeName . DIRECTORY_SEPARATOR . $this->_ratio;
        } else {
            $this->_targetImagePath = $this->cachePath . DIRECTORY_SEPARATOR . $this->_model::tableName() . DIRECTORY_SEPARATOR . $this->_sizeName;
        }

        if (!is_dir($this->_targetImagePath)) {
            FileHelper::createDirectory($this->_targetImagePath);
        }

        $this->_targetImageFileName = $this->_targetImagePath . DIRECTORY_SEPARATOR . call_user_func($this->getImageBaseName, $this->_model);

    }

    public function run()
    {
        if (!file_exists($this->_targetImageFileName)) {
            if ($this->getOriginImageFileName) {
                $this->_originImageFileName = call_user_func($this->getOriginImageFileName, $this->_model);
            }

            $imgsize = getimagesize($this->_originImageFileName);
            $width = $imgsize[0];
            $height = $imgsize[1];
            $minSize = min($width, $height);

            $ratioMultiplyer = $this->_ratio ? self::RATIOS[$this->_ratio] : 1;

            Image::crop($this->_originImageFileName, $minSize, $minSize)
                ->resize(new Box($this->_size * $ratioMultiplyer, $this->_size * $ratioMultiplyer))
                ->save($this->_targetImageFileName);
        }

        return Yii::$app->getResponse()->sendFile($this->_targetImageFileName, null, [
            'mimeType' => mime_content_type($this->_targetImageFileName),
            'inline' => true,
        ]);
    }

}

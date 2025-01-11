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
use yii\base\Exception;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use yii\web\NotFoundHttpException;

class ImageAction extends Action
{
    const TYPE_QUAD = 'quad';
    const TYPE_WIDTH = 'width';
    const TYPE_HEIGHT = 'height';

    const RATIO_1X = '1x';
    const RATIO_2X = '2x';
    const RATIO_3X = '3x';

    const RATIOS = [self::RATIO_1X => 1, self::RATIO_2X => 2, self::RATIO_3X => 3];

    public string $type = self::TYPE_QUAD;
    public array $sizes;
    public Closure $getOriginImageFileName;
    public Closure $getImageBaseName;
    public ?Closure $handleImageNotFound = null;
    public string $cachePath;
    public string $modelClass;

    private string $_sizeName;
    private int $_size;
    private ?string $_ratio = null;
    private ?ActiveRecord $_model;
    private string $_originImageFileName;
    private string $_targetImagePath;
    private string $_targetImageFileName;
    private ?string $_targetImageFileNameWebp = null;


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

        if ($this->_ratio = (string)Yii::$app->request->get('ratio')) {
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

        if ($imageBaseName = call_user_func($this->getImageBaseName, $this->_model)) {
            $this->_targetImageFileName = $this->_targetImagePath . DIRECTORY_SEPARATOR . $imageBaseName;
        } else {
            throw new NotFoundHttpException();
        }

        $requestedFilename = (string)Yii::$app->request->get('filename');

        if (pathinfo($requestedFilename, PATHINFO_EXTENSION) == 'webp') {
            $this->_targetImageFileNameWebp = $this->_targetImagePath . DIRECTORY_SEPARATOR . $requestedFilename;
        }
    }

    public function run()
    {
        if (!file_exists($this->_targetImageFileName) || ($this->_targetImageFileNameWebp && !file_exists($this->_targetImageFileNameWebp))) {
            if ($this->getOriginImageFileName) {
                $this->_originImageFileName = call_user_func($this->getOriginImageFileName, $this->_model);
            }

            if (!file_exists($this->_originImageFileName)) {
                if ($this->handleImageNotFound) {
                    call_user_func($this->handleImageNotFound, $this->_model);
                }
            }

            $imgsize = getimagesize($this->_originImageFileName);
            $originWidth = $imgsize[0];
            $originHeight = $imgsize[1];

            $ratioMultiplyer = $this->_ratio ? self::RATIOS[$this->_ratio] : 1;

            if ($this->type == self::TYPE_QUAD) {
                $minOriginSideSize = min($originWidth, $originHeight);
                $image = Image::crop($this->_originImageFileName, $minOriginSideSize, $minOriginSideSize);
                $newSideSize = $this->_size * $ratioMultiplyer;
                if ($newSideSize < $minOriginSideSize) {
                    $image->resize(new Box($newSideSize, $newSideSize));
                }

            } elseif ($this->type == self::TYPE_WIDTH) {
                $factor = $originWidth / $this->_size;
                $image = Image::resize($this->_originImageFileName, $this->_size * $ratioMultiplyer, $originHeight * $factor * $ratioMultiplyer);
            } elseif ($this->type == self::TYPE_HEIGHT) {
                $factor = $originHeight / $this->_size;
                $image = Image::resize($this->_originImageFileName, $originWidth * $factor * $ratioMultiplyer, $this->_size * $ratioMultiplyer);
            } else {
                throw new Exception('unknown type');
            }
            $image->save($this->_targetImageFileName);

            if ($this->_targetImageFileNameWebp) {
                shell_exec("cwebp -q 80 {$this->_targetImageFileName} -o {$this->_targetImageFileNameWebp}");
            }
        }

        return Yii::$app->response->sendFile($this->_targetImageFileNameWebp ?? $this->_targetImageFileName, null, [
            'mimeType' => mime_content_type($this->_targetImageFileName),
            'inline' => true,
        ]);
    }

}

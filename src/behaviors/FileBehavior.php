<?php
/**
 * Created by PhpStorm.
 * User: Алимжан
 * Date: 27.01.2015
 * Time: 12:24
 */

namespace sampa\media\behaviors;

use sampa\media\models\File;
use sampa\media\ModuleTrait;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\UploadedFile;
use yii\helpers\Json;

class FileBehavior extends Behavior
{
    use ModuleTrait;

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'saveUploads',
            ActiveRecord::EVENT_AFTER_UPDATE => 'saveUploads',
            ActiveRecord::EVENT_AFTER_DELETE => 'deleteUploads'
        ];
    }

    public function saveUploads($event)
    {
        $files = UploadedFile::getInstancesByName('UploadForm[file]');

        if (!empty($files)) {
            foreach ($files as $file) {
                if (!$file->saveAs($this->getModule()->getUserDirPath() . $file->name)) {
                    throw new \Exception(\Yii::t('yii', 'File upload failed.'));
                }
            }
        }

        $userTempDir = $this->getModule()->getUserDirPath();
        foreach (FileHelper::findFiles($userTempDir) as $file) {
            if (!$this->getModule()->attachFile($file, $this->owner)) {
                throw new \Exception(\Yii::t('yii', 'File upload failed.'));
            }
        }
        rmdir($userTempDir);
    }

    public function deleteUploads($event)
    {
        foreach ($this->getFiles() as $file) {
            $this->getModule()->detachFile($file->id);
        }
    }

    public function fileQuery(){

        $fileQuery = File::find()
            ->where([
                'itemId' => $this->owner->id,
                'model' => $this->getModule()->getShortClass($this->owner)
            ]);
        $fileQuery->orderBy(['id' => SORT_ASC]);

        return $fileQuery;
    }
    /**
     * @return File[]
     * @throws \Exception
     */
    public function getFiles()
    {
        return $this->fileQuery()->all();
    }
    /**
     * @return File[] images only
     * @throws \Exception
     */
    public function getImageFiles()
    {

        $fileQuery = $this->fileQuery();
        $imageFiles = $fileQuery->andFilterWhere(['like', 'mime', 'image'])->all();

        return $imageFiles;
    }

    /**
 * @return File[] images only in format useable for gallery widget by dosamigos
 * @throws \Exception
 */
    public function getImageGalleryFiles()
    {
        $images = $this->getImageFiles();
        $items = [];
        foreach ($images as $image){
            $items[] = [
                'url' => $image->url,
                'src' => $image->url, //thumbnail path
                'options' => array('title' => $image->name)
            ];
        }
        return $items;
    }

    /**
     * @return File[] plain text only
     * @throws \Exception
     */
    public function getTextFiles()
    {
        $fileQuery = $this->fileQuery();
        $textFiles =  $fileQuery->andFilterWhere(['like', 'mime', 'text/plain'])->all();

        return $textFiles;
    }

    public function getPdfFiles()
    {
        $fileQuery = $this->fileQuery();
        $pdfFiles = $fileQuery->andFilterWhere(['like', 'mime', 'application/pdf'])->all();
    }
    public function getInitialPreview()
    {
        $initialPreview = [];

        $userTempDir = $this->getModule()->getUserDirPath();
        foreach (FileHelper::findFiles($userTempDir) as $file) {
            if (substr(FileHelper::getMimeType($file), 0, 5) === 'image') {
                $initialPreview[] = Html::img(['/media/file/download-temp', 'filename' => basename($file)], ['class' => 'file-preview-image']);
            } else {
                $initialPreview[] = Html::beginTag('div', ['class' => 'file-preview-other']) .
                    Html::beginTag('h2') .
                    Html::tag('i', '', ['class' => 'glyphicon glyphicon-file']) .
                    Html::endTag('h2') .
                    Html::endTag('div');
            }
        }

        foreach ($this->getFiles() as $file) {
            if (substr($file->mime, 0, 5) === 'image') {
                $initialPreview[] = Html::img($file->getUrl(), ['class' => 'file-preview-image']);
            } else {
                $initialPreview[] = Html::beginTag('div', ['class' => 'file-preview-other']) .
                    Html::beginTag('h2') .
                    Html::tag('i', '', ['class' => 'glyphicon glyphicon-file']) .
                    Html::endTag('h2') .
                    Html::endTag('div');
            }
        }

        return $initialPreview;
    }

    public function getInitialPreviewConfig()
    {
        $initialPreviewConfig = [];

        $userTempDir = $this->getModule()->getUserDirPath();
        foreach (FileHelper::findFiles($userTempDir) as $file) {
            $filename = basename($file);
            $initialPreviewConfig[] = [
                'caption' => $filename,
                'url' => Url::to(['/media/file/delete-temp',
                    'filename' => $filename
                ]),
            ];
        }

        foreach ($this->getFiles() as $index => $file) {
            $initialPreviewConfig[] = [
                'caption' => "$file->name.$file->type",
                'url' => Url::toRoute(['/media/file/delete',
                    'id' => $file->id
                ]),
            ];
        }

        return $initialPreviewConfig;
    }
}
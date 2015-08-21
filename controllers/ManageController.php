<?php

namespace yeesoft\media\controllers;

use yeesoft\controllers\admin\BaseController;
use yeesoft\media\assets\MediaAsset;
use yeesoft\media\MediaModule;
use yeesoft\media\models\Media;
use yeesoft\models\User;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Response;

class ManageController extends BaseController
{
    public $enableCsrfValidation = false;

    public $disabledActions = ['view', 'create', 'toggle-attribute', 'bulk-activate',
        'bulk-deactivate', 'bulk-delete', 'grid-sort', 'grid-page-size'];

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                    'update' => ['post'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        \Yii::$app->assetManager->forceCopy = true;
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $this->layout = '@vendor/yeesoft/yii2-yee-media/views/layouts/main';
        $model = new Media();
        $dataProvider = $model->search();
        $dataProvider->pagination->defaultPageSize = 15;

        return $this->render('index',
            [
                'model' => $model,
                'dataProvider' => $dataProvider,
            ]
        );
    }

    public function actionUploader()
    {
        $this->layout = '@vendor/yeesoft/yii2-yee-media/views/layouts/main';
        return $this->render('uploader', ['model' => new Media()]);
    }

    /**
     * Provides upload file
     * @return mixed
     */
    public function actionUpload()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $model = new Media();
        $routes = $this->module->routes;
        $rename = $this->module->rename;
        $model->saveUploadedFile($routes, $rename);
        $bundle = MediaAsset::register($this->view);

        if ($model->isImage()) {
            $model->createThumbs($routes, $this->module->thumbs);
        }

        $response['files'][] = [
            'url' => $model->url,
            'thumbnailUrl' => $model->getDefaultThumbUrl($bundle->baseUrl),
            'name' => $model->filename,
            'type' => $model->type,
            'size' => $model->file->size,
            'deleteUrl' => Url::to(['manage/delete', 'id' => $model->id]),
            'deleteType' => 'POST',
        ];

        return $response;
    }

    /**
     * Updated media by id
     * @param $id
     * @return array
     */
    public function actionUpdate($id)
    {
        $model = Media::findOne($id);
        $message = MediaModule::t('main', 'Changes not saved.');

        if (User::hasPermission('editMedia')) {
            if ($model->load(Yii::$app->request->post()) && $model->save()) {
                $message = MediaModule::t('main', 'Changes saved!');
            }

            Yii::$app->session->setFlash('mediaUpdateResult', $message);
        } else {
            die(MediaModule::t('main', 'You are not allowed to perform this action.'));
        }

        return $this->renderPartial('info',
            [
                'model' => $model,
                'strictThumb' => null,
            ]);
    }

    /**
     * Delete model with medias
     * @param $id
     * @return array
     */
    public function actionDelete($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $routes = $this->module->routes;

        $model = Media::findOne($id);

        if (User::hasPermission('deleteMedia')) {
            if ($model->isImage()) {
                $model->deleteThumbs($routes);
            }

            $model->deleteFile($routes);
            $model->delete();

            return ['success' => 'true'];

        } else {
            die(MediaModule::t('main', 'You are not allowed to perform this action.'));
        }
    }

    /**
     * Resize all thumbnails
     */
    public function actionResize()
    {
        $models = Media::findByTypes(Media::$imageFileTypes);
        $routes = $this->module->routes;

        foreach ($models as $model) {
            if ($model->isImage()) {
                $model->deleteThumbs($routes);
                $model->createThumbs($routes, $this->module->thumbs);
            }
        }

        Yii::$app->session->setFlash('successResize');
        $this->redirect(Url::to(['default/settings']));
    }

    /** Render model info
     * @param int $id
     * @param string $strictThumb only this thumb will be selected
     * @return string
     */
    public function actionInfo($id, $strictThumb = null)
    {
        $model = Media::findOne($id);
        return $this->renderPartial('info',
            [
                'model' => $model,
                'strictThumb' => $strictThumb,
            ]);
    }
}
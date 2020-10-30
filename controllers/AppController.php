<?php
/**
 * Copyright (C) Baluart.COM - All Rights Reserved
 *
 * @since 1.0
 * @author Balu
 * @copyright Copyright (c) 2015 - 2019 Baluart.COM
 * @license http://codecanyon.net/licenses/faq Envato marketplace licenses
 * @link http://easyforms.baluart.com/ Easy Forms
 */

namespace app\controllers;

use Yii;
use yii\filters\ContentNegotiator;
use yii\web\Response;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use app\helpers\Language;
use app\helpers\UrlHelper;
use app\helpers\SlugHelper;
use app\helpers\ArrayHelper;
use app\helpers\ImageHelper;
use app\helpers\SubmissionHelper;
use app\events\SubmissionEvent;
use app\models\Form;
use app\models\Theme;
use app\models\FormSubmission;
use app\models\FormConfirmation;
use app\models\FormSubmissionFile;
use app\models\forms\RestrictedForm;
use app\components\analytics\Analytics;
use app\components\filters\DynamicCors;

/**
 * Class AppController
 * @package app\controllers
 */
class AppController extends Controller
{

    /**
     * @inheritdoc
     */
    public $defaultAction = 'form';

    /** @var null|Form  */
    private $formModel = null;

    /**
     * @event SubmissionEvent an event fired when a submission is received.
     */
    const EVENT_SUBMISSION_RECEIVED = 'app.form.submission.received';

    /**
     * @event SubmissionEvent an event fired when a submission is accepted.
     */
    const EVENT_SUBMISSION_ACCEPTED = 'app.form.submission.accepted';

    /**
     * @event SubmissionEvent an event fired when a submission is rejected by validation errors.
     */
    const EVENT_SUBMISSION_REJECTED = 'app.form.submission.rejected';

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'corsFilter' => [
                'class' => DynamicCors::className(),
                'only' => ['check', 'f'],
                'cors' => [
                    // Restrict access to
                    // 'Origin' => ['http://www.example.com', 'https://www.example.com'],
                    // 'Origin' => ['*'],
                    'Origin' => function () {
                        if ($this->formModel && $this->formModel->authorized_urls === Form::ON && !empty($this->formModel->urls)) {
                            $urls = array_map('trim', explode(',', $this->formModel->urls));
                            $origin = [];
                            foreach ($urls as $url) {
                                $origin[] = UrlHelper::addScheme($url, 'http');
                                $origin[] = UrlHelper::addScheme($url, 'https');
                            }
                            return array_unique($origin);
                        }
                        return ['*'];
                    },
                    // Allow only POST method
                    'Access-Control-Request-Method' => ['POST'],
                    // Allow only headers 'X-Wsse'
                    'Access-Control-Request-Headers' => ['*'],
                    // Allow credentials (cookies, authorization headers, etc.) to be exposed to the browser
                    'Access-Control-Allow-Credentials' => null,
                    // Allow OPTIONS caching
                    'Access-Control-Max-Age' => 86400,
                    // Allow the X-Pagination-Current-Page header to be exposed to the browser.
                    'Access-Control-Expose-Headers' => [],
                ],
            ],
            [
                'class' => ContentNegotiator::className(),
                'only' => ['f'],
                'formats' => [
                    'text/html' => Response::FORMAT_HTML,
                    'application/json' => Response::FORMAT_JSON,
                    'application/xml' => Response::FORMAT_XML,
                ],
                'languages' => array_merge(['en-US'], array_diff(array_keys(Language::supportedLanguages()), ['en-US'])),
            ],
        ];
    }

    /**
     * This method is invoked right before an action is executed.
     *
     * @param $action
     * @return bool
     * @throws NotFoundHttpException
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action)
    {

        if ($id = Yii::$app->request->get('id')) {
            // If no Form model, throw NotFoundHttpException
            $this->formModel = $this->findFormModel($id);
            // Change default language of form messages by the selected form language
            Yii::$app->language = $this->formModel->language;
        }

        // Disable CSRF Validation to use Authorized URLs and CORS
        if (in_array($action->id, ['check', 'f'])) {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * Display json array of validation errors
     *
     * @param int $id Form ID
     * @return array|string Validation errors
     */
    public function actionCheck($id)
    {
        $this->layout = 'public';

        if (Yii::$app->request->isAjax) {
            // Set public scenario of the submission
            $formSubmissionModel = new FormSubmission(['scenario' => 'public']);
            // The HTTP post request
            $post = Yii::$app->request->post();
            // Prepare Submission to Save in DB
            $postFormSubmission = [
                'FormSubmission' => [
                    'form_id' => $this->formModel->id, // Form Model id
                    'data' => $post, // (array)
                ]
            ];
            // Perform validations
            if ($formSubmissionModel->load($postFormSubmission)) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                $formSubmissionModel->validate();
                $result = [];
                foreach ($formSubmissionModel->getErrors() as $attribute => $errors) {
                    $result[$attribute] = $errors;
                }
                return $result;
            }
        }

        return $this->render('endpoint', [
            'success' => false,
            'message' => Yii::t('app', 'There is {startTag}an error in your submission{endTag}.', [
                'startTag' => '<strong>',
                'endTag' => '</strong>',
            ]),
            'formModel' => $this->formModel,
        ]);
    }

    /**
     * Displays a single Form Data model for preview
     *
     * @param $id
     * @param null $theme_id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionPreview($id, $theme_id = null)
    {
        $this->layout = "public";

        $formModel = $this->formModel;
        $formDataModel = $formModel->formData;

        $themeModel = null;
        if (isset($theme_id) && $theme_id > 0) {
            $themeModel = Theme::findOne($theme_id);
        }

        return $this->render('preview', [
            'formModel' => $formModel,
            'formDataModel' => $formDataModel,
            'themeModel' => $themeModel
        ]);
    }

    /**
     * Displays a single Form model.
     *
     * @param int $id Form ID
     * @param int $t Show / Hide CSS theme
     * @param int $b Show / Hide Form Box
     * @param int $js Load Custom Javascript File
     * @param int $rec Record stats. Enable / Disable record stats dynamically
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionForm($id, $t = 1, $b = 1, $js = 1, $rec = 1)
    {

        $this->layout = 'public';

        $formModel = $this->formModel;
        $formDataModel = $formModel->formData;

        $showTheme = $t > 0 ? 1 : 0;
        $showBox = $b > 0 ? 1 : 0;
        $customJS = $js > 0 ? 1 : 0;
        $record = $rec > 0 ? 1 : 0;

        return $this->render('form', [
            'formModel' => $formModel,
            'formDataModel' => $formDataModel,
            'showTheme' => $showTheme,
            'showBox' => $showBox,
            'customJS' => $customJS,
            'record' => $record,
        ]);

    }

    /**
     * Displays a single Form model.
     *
     * @param string $slug
     * @param int $t Show / Hide CSS theme
     * @param int $b Show / Hide Form Box
     * @param int $js Load Custom Javascript File
     * @param int $rec Record stats. Enable / Disable record stats dynamically
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionForms($slug, $t = 1, $b = 1, $js = 1, $rec = 1)
    {

        $this->layout = 'public';

        $showTheme = $t > 0 ? 1 : 0;
        $showBox = $b > 0 ? 1 : 0;
        $customJS = $js > 0 ? 1 : 0;
        $record = $rec > 0 ? 1 : 0;

        /** @var Form $formModel */
        if (($this->formModel = Form::findOne(['slug'=>$slug])) !== null) {
            $formDataModel = $this->formModel->formData;
            return $this->render('form', [
                'formModel' => $this->formModel,
                'formDataModel' => $formDataModel,
                'showTheme' => $showTheme,
                'showBox' => $showBox,
                'customJS' => $customJS,
                'record' => $record,
            ]);
        }

        throw new NotFoundHttpException(Yii::t("app", "The requested page does not exist."));
    }

    /**
     * Displays a single Form Data Model for Embed.
     *
     * @param $id
     * @param int $t Show / Hide CSS theme
     * @param int $js Load Custom Javascript File
     * @param int $rec Record stats. Enable / Disable record stats dynamically
     * @return string
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionEmbed($id, $t = 1, $js = 1, $rec = 1)
    {

        $this->layout = 'public';

        $formModel = $this->formModel;

        $showTheme = $t > 0 ? 1 : 0;
        $customJS = $js > 0 ? 1 : 0;
        $record = $rec > 0 ? 1 : 0;

        // Check Authorized URLs
        $formModel->checkAuthorizedUrls();

        // Check Form Activity to update Form Status
        $formModel->checkFormActivity();

        // Display Message when Form is Inactive
        if ($formModel->status === $formModel::STATUS_INACTIVE) {
            return $this->render('message', [
                'formModel' => $formModel,
            ]);
        }

        // Restrict access when form is password protected
        if ($formModel->use_password === $formModel::ON) {

            $restrictedForm = new RestrictedForm();

            if (!$restrictedForm->load(Yii::$app->request->post()) || !$restrictedForm->validate()) {
                return $this->render('restricted', [
                    'model' => $restrictedForm,
                    'formModel' => $formModel,
                ]);
            }
        }

        $formDataModel = $formModel->formData;
        $formConfirmationModel = $formModel->formConfirmation;
        $formRuleModels = $formModel->getActiveRules()->createCommand()->queryAll();

        return $this->render('embed', [
            'formModel' => $formModel,
            'formDataModel' => $formDataModel,
            'formConfirmationModel' => $formConfirmationModel,
            'formRuleModels' => $formRuleModels,
            'showTheme' => $showTheme,
            'customJS' => $customJS,
            'record' => $record,
        ]);

    }

    /**
     * Insert a Form Submission Model
     *
     * @param $id
     * @return array|string
     * @throws NotFoundHttpException
     * @throws \yii\base\InvalidConfigException
     * @deprecated 1.7.1 This method will be removed in v1.7.2, use actionF() instead
     */
    public function actionA($id)
    {
        if (Yii::$app->request->isAjax) {

            // Global HTTP response body
            Yii::$app->params['Form.Response'] = [];

            // The HTTP post request
            $post = Yii::$app->request->post();

            if (isset($post)) {

                $formModel = $this->formModel;

                /*******************************
                /* Authorized URLs
                /*******************************/

                // Check Authorized URLs
                $formModel->checkAuthorizedUrls();

                /*******************************
                /* Spam Filter
                /*******************************/

                // Honeypot filter. If spam, throw NotFoundHttpException
                $formModel->checkHoneypot($post);

                // reCAPTCHA Validation. If error, send response to browser
                $formModel->validateRecaptcha($post);

                /*******************************
                /* Submission Limit
                /*******************************/

                // If error, send response to browser
                $formModel->checkTotalLimit();
                $formModel->checkIPLimit();

                /*******************************
                /* Prepare response by default
                /*******************************/

                // Response fornat
                Yii::$app->response->format = Response::FORMAT_JSON;

                // Default response
                $response = array(
                    'action'  => 'submit',
                    'success' => true,
                    'id' => 0,
                    'message' => Yii::t('app', 'Your message has been sent. {startTag}Thank you!{endTag}', [
                        'startTag' => '<strong>',
                        'endTag' => '</strong>',
                    ]),
                );

                /*******************************
                /* Prepare data
                /*******************************/

                // Set public scenario of the submission
                $formSubmissionModel = new FormSubmission(['scenario' => 'public']);

                /** @var \app\models\FormData $formDataModel */
                $formDataModel = $formModel->formData;
                // Get all fields except buttons and files
                $fields = $formDataModel->getFieldsWithoutFilesAndButtons();
                // Get file fields
                $fileFields = $formDataModel->getFileLabels();

                // Remove fields with null values and
                // Strip whitespace from the beginning and end of each post value
                $submissionData = $formSubmissionModel->cleanSubmission($fields, $post);
                // Get uploaded files
                $uploadedFiles = $formSubmissionModel->getUploadedFiles($fileFields);
                // File paths cache
                $filePaths = array();

                // Prepare Submission for validation
                $postFormSubmission = [
                    'FormSubmission' => [
                        'form_id' => $formModel->id, // Form Model id
                        'data' => $submissionData, // (array)
                    ]
                ];

                /*******************************
                /* FormSubmission Validation
                /*******************************/

                if ($formSubmissionModel->load($postFormSubmission) && $formSubmissionModel->validate()) {

                    Yii::$app->trigger($this::EVENT_SUBMISSION_RECEIVED, new SubmissionEvent([
                        'sender' => $this,
                        'form' => $formModel,
                        'submission' => $formSubmissionModel,
                        'files' => $uploadedFiles,
                    ]));

                    if ($formModel->saveToDB()) {

                        /*******************************
                        /* Save to DB
                        /*******************************/

                        // Save submission in single transaction
                        $transaction = Form::getDb()->beginTransaction();

                        try {

                            // Save submission without validation
                            if ($formSubmissionModel->save(false)) {

                                // Save files to DB and disk

                                /* @var $file \yii\web\UploadedFile */
                                foreach ($uploadedFiles as $uploadedFile) {
                                    if (isset($uploadedFile['name'], $uploadedFile['label'], $uploadedFile['file'])) {
                                        /* @var $file \yii\web\UploadedFile */
                                        $file = $uploadedFile['file'];
                                        // Save file to DB
                                        $fileModel = new FormSubmissionFile();
                                        $fileModel->submission_id = $formSubmissionModel->primaryKey;
                                        $fileModel->form_id = $formModel->id;
                                        $fileModel->field = $uploadedFile['name'];
                                        $fileModel->label = $uploadedFile['label'];
                                        // Replace special characters before the file is saved
                                        $fileModel->name = SlugHelper::slug($file->baseName) . "-" . rand(0, 100000) .
                                            "-" . $formSubmissionModel->primaryKey;
                                        $fileModel->extension = $file->extension;
                                        $fileModel->size = $file->size;
                                        $fileModel->status = 1;
                                        $fileModel->save();

                                        // Throw exception if validation fail
                                        if (isset($fileModel->errors) && count($fileModel->errors) > 0) {
                                            throw new \Exception(Yii::t("app", "Error saving files."));
                                        }

                                        // Save file to disk
                                        $filePath = $fileModel->getFilePath();
                                        $file->saveAs($filePath);

                                        // Enable Image compression
                                        if (ImageHelper::isImage($filePath)) {
                                            // Check if the configuration exists
                                            if (isset(Yii::$app->params['Form.Uploads.imageCompression']) && Yii::$app->params['Form.Uploads.imageCompression'] > 0) {
                                                // Compress image
                                                $compressed = ImageHelper::compress($filePath, Yii::$app->params['Form.Uploads.imageCompression']);
                                                // Save new file size
                                                if ($compressed) {
                                                    $fileModel->size = filesize(Yii::getAlias("@app") . DIRECTORY_SEPARATOR . $filePath);
                                                    $fileModel->save();
                                                }
                                            }
                                        }

                                        array_push($filePaths, $filePath);
                                    }
                                }

                                // Change response id
                                $response["id"] = $formSubmissionModel->primaryKey;

                            }

                            $transaction->commit();

                        } catch (\Exception $e) {
                            // Rolls back the transaction
                            $transaction->rollBack();
                            // Rethrow the exception
                            throw $e;
                        }

                    } else {

                        /*******************************
                        /* Don't save to DB
                        /*******************************/

                        // Save files to disk
                        foreach ($uploadedFiles as $uploadedFile) {
                            if (isset($uploadedFile['file'])) {
                                /* @var $file \yii\web\UploadedFile */
                                $file = $uploadedFile['file'];
                                $fileName = SlugHelper::slug($file->baseName) . "-" . rand(0, 100000) . "." . $file->extension;
                                $filePath = $formModel::FILES_DIRECTORY . '/' . $formModel->id . '/' . $fileName;
                                $file->saveAs($filePath);
                                // Enable Image compression
                                if (ImageHelper::isImage($filePath)) {
                                    // Check if the configuration exists
                                    if (isset(Yii::$app->params['Form.Uploads.imageCompression']) && Yii::$app->params['Form.Uploads.imageCompression'] > 0) {
                                        // Compress image
                                        ImageHelper::compress($filePath, Yii::$app->params['Form.Uploads.imageCompression']);
                                    }
                                }
                                array_push($filePaths, $filePath);
                            }
                        }
                    }

                    // Custom Thank you Message

                    /** @var \app\models\FormConfirmation $formConfirmationModel */
                    $formConfirmationModel = $formModel->formConfirmation;

                    // Form fields
                    $fieldsForEmail = $formDataModel->getFieldsForEmail();

                    // Update submission data with additional information like the submission_id, form_id and more
                    if ($formModel->saveToDB()) {
                        $submissionData = $formSubmissionModel->getSubmissionData();
                    }

                    // Submission data in an associative array
                    $fieldValues = SubmissionHelper::prepareDataForReplacementToken($submissionData, $fieldsForEmail);

                    // Replace tokens in Confirmation url
                    if ($formConfirmationModel->type == $formConfirmationModel::CONFIRM_WITH_REDIRECTION && !empty($formConfirmationModel->url)) {
                        $response['confirmationUrl'] = SubmissionHelper::replaceTokens($formConfirmationModel->url, $fieldValues);
                    }
                    // Replace tokens in Confirmation message
                    elseif (!empty($formConfirmationModel->message)) {
                        $response['message'] = SubmissionHelper::replaceTokens($formConfirmationModel->message, $fieldValues);
                    }

                    Yii::$app->trigger($this::EVENT_SUBMISSION_ACCEPTED, new SubmissionEvent([
                        'sender' => $this,
                        'form' => $formModel,
                        'submission' => $formSubmissionModel,
                        'files' => $uploadedFiles,
                        'filePaths' => $filePaths,
                    ]));

                } else {

                    Yii::$app->trigger($this::EVENT_SUBMISSION_REJECTED, new SubmissionEvent([
                        'sender' => $this,
                        'form' => $formModel,
                        'submission' => $formSubmissionModel,
                    ]));

                    // Print validation errors
                    $errors = array();
                    foreach ($formSubmissionModel->errors as $field => $messages) {
                        array_push($errors, array(
                            "field" => $field,
                            "messages" => $messages,
                        ));
                    }

                    // Change response
                    $response["success"] = false;
                    $response["message"] = Yii::t('app', 'There is {startTag}an error in your submission{endTag}.', [
                        'startTag' => '<strong>',
                        'endTag' => '</strong>',
                    ]);
                    $response["errors"] = $errors;

                }

                return array_merge($response, Yii::$app->params['Form.Response']);

            }
        }

        return $this->render('endpoint', [
            'success' => false,
            'message' => Yii::t('app', 'There is {startTag}an error in your submission{endTag}.', [
                'startTag' => '<strong>',
                'endTag' => '</strong>',
            ]),
            'formModel' => $this->formModel,
        ]);
    }

    /**
     * Form EndPoint
     * Features:
     * - Insert a Form Submission Model
     * - Send response in different formats (HTML, JSON, XML)
     * - CORS Integration with Authorized Urls
     *
     * @param $id
     * @return array|string|Response
     * @throws NotFoundHttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionF($id)
    {
        $this->layout = 'public';

        if (Yii::$app->request->isPost) {

            // Global HTTP response body
            Yii::$app->params['Form.Response'] = [];

            // The HTTP post request
            $post = Yii::$app->request->post();

            if (!empty($post)) {

                $formModel = $this->formModel;

                /*******************************
                /* Prepare response by default
                /*******************************/
                // Language
                Yii::$app->language = $formModel->language;

                // Default response
                $response = array(
                    'action'  => 'submit',
                    'success' => true,
                    'id' => 0,
                    'message' => Yii::t('app', 'Your message has been sent. {startTag}Thank you!{endTag}', [
                        'startTag' => '<strong>',
                        'endTag' => '</strong>',
                    ]),
                    'errors' => [],
                );

                /*******************************
                /* Authorized URLs
                /*******************************/

                // Check Authorized URLs. If not authorized, throw NotFoundHttpException
                $formModel->checkAuthorizedUrls();

                /*******************************
                /* Spam Filter
                /*******************************/

                // Honeypot filter. If spam, throw NotFoundHttpException
                $formModel->checkHoneypot($post);

                // reCAPTCHA Validation.
                if ($response['success']) {
                    $v = $formModel->validateRecaptcha($post);
                    if (isset($v['success'], $v['errorMessage'], $v['error']) && $v['success'] === false) {
                        $response['success'] = false;
                        $response['message'] = $v['errorMessage'];
                        array_push($response['errors'], $v['error']);
                    }
                }

                /*******************************
                /* Submission Limit
                /*******************************/
                if ($response['success']) {
                    $v = $formModel->checkTotalLimit();
                    if (isset($v['success'], $v['errorMessage']) && $v['success'] === false) {
                        $response['success'] = false;
                        $response['message'] = $v['errorMessage'];
                    }
                }

                if ($response['success']) {
                    $v = $formModel->checkIPLimit();
                    if (isset($v['success'], $v['errorMessage']) && $v['success'] === false) {
                        $response['success'] = false;
                        $response['message'] = $v['errorMessage'];
                    }
                }

                /*******************************
                /* Form Activity
                /*******************************/
                if ($response['success']) {
                    $formModel->checkFormActivity();
                    if ($formModel->status === $formModel::STATUS_INACTIVE) {
                        $response['success'] = false;
                        $response['message'] = Yii::t('app', 'This form is no longer accepting new submissions.');
                    }
                }

                /*******************************
                /* Prepare data
                /*******************************/

                /** @var \app\models\FormData $formDataModel */
                $formDataModel = $formModel->formData;
                // Get all fields except buttons and files
                $fields = $formDataModel->getFieldsWithoutFilesAndButtons();
                // Get file fields
                $fileFields = $formDataModel->getFileFields();
                // Get file labels
                $fileLabels = $formDataModel->getFileLabels();

                // Replace Field Alias with Field Name in POST data and FILES
                foreach ($fields as $field) {
                    if (!empty($field['alias'])) {
                        ArrayHelper::replaceKey($post, $field['alias'], $field['name']);
                    }
                }

                // Replace Field Alias with Field Name in POST data and FILES
                foreach ($fileFields as $field) {
                    if (!empty($field['alias'])) {
                        ArrayHelper::replaceKey($_FILES, $field['alias'], $field['name']);
                    }
                }

                // Set public scenario of the submission
                $formSubmissionModel = new FormSubmission(['scenario' => 'public']);

                // Remove fields with null values and
                // Strip whitespace from the beginning and end of each post value
                $submissionData = $formSubmissionModel->cleanSubmission($fields, $post);
                // Get uploaded files
                $uploadedFiles = $formSubmissionModel->getUploadedFiles($fileLabels);

                // File paths cache
                $filePaths = array();

                // Prepare Submission for validation
                $postFormSubmission = [
                    'FormSubmission' => [
                        'form_id' => $formModel->id, // Form Model id
                        'data' => $submissionData, // (array)
                    ]
                ];

                /*******************************
                /* FormSubmission Validation
                /*******************************/

                if ($response['success'] && $formSubmissionModel->load($postFormSubmission) && $formSubmissionModel->validate()) {

                    Yii::$app->trigger($this::EVENT_SUBMISSION_RECEIVED, new SubmissionEvent([
                        'sender' => $this,
                        'form' => $formModel,
                        'submission' => $formSubmissionModel,
                        'files' => $uploadedFiles,
                    ]));

                    if ($formModel->saveToDB()) {

                        /*******************************
                        /* Save to DB
                        /*******************************/

                        // Save submission in single transaction
                        $transaction = Form::getDb()->beginTransaction();

                        try {

                            // Save submission without validation
                            if ($formSubmissionModel->save(false)) {

                                // Save files to DB and disk

                                /* @var $file \yii\web\UploadedFile */
                                foreach ($uploadedFiles as $uploadedFile) {
                                    if (isset($uploadedFile['name'], $uploadedFile['label'], $uploadedFile['file'])) {
                                        /* @var $file \yii\web\UploadedFile */
                                        $file = $uploadedFile['file'];
                                        // Save file to DB
                                        $fileModel = new FormSubmissionFile();
                                        $fileModel->submission_id = $formSubmissionModel->primaryKey;
                                        $fileModel->form_id = $formModel->id;
                                        $fileModel->field = $uploadedFile['name'];
                                        $fileModel->label = $uploadedFile['label'];
                                        // Replace special characters before the file is saved
                                        $fileModel->name = SlugHelper::slug($file->baseName) . "-" . rand(0, 100000) .
                                            "-" . $formSubmissionModel->primaryKey;
                                        $fileModel->extension = $file->extension;
                                        $fileModel->size = $file->size;
                                        $fileModel->status = 1;
                                        $fileModel->save();

                                        // Throw exception if validation fail
                                        if (isset($fileModel->errors) && count($fileModel->errors) > 0) {
                                            throw new \Exception(Yii::t("app", "Error saving files."));
                                        }

                                        // Save file to disk
                                        $filePath = $fileModel->getFilePath();
                                        $file->saveAs($filePath);

                                        // Enable Image compression
                                        if (ImageHelper::isImage($filePath)) {
                                            // Check if the configuration exists
                                            if (isset(Yii::$app->params['Form.Uploads.imageCompression']) && Yii::$app->params['Form.Uploads.imageCompression'] > 0) {
                                                // Compress image
                                                $compressed = ImageHelper::compress($filePath, Yii::$app->params['Form.Uploads.imageCompression']);
                                                // Save new file size
                                                if ($compressed) {
                                                    $fileModel->size = filesize(Yii::getAlias("@app") . DIRECTORY_SEPARATOR . $filePath);
                                                    $fileModel->save();
                                                }
                                            }
                                        }

                                        array_push($filePaths, $filePath);
                                    }
                                }

                                // Change response id
                                $response["id"] = $formSubmissionModel->primaryKey;

                            }

                            $transaction->commit();

                        } catch (\Exception $e) {
                            // Rolls back the transaction
                            $transaction->rollBack();
                            // Rethrow the exception
                            throw $e;
                        }

                    } else {

                        /*******************************
                        /* Don't save to DB
                        /*******************************/

                        // Save files to disk
                        foreach ($uploadedFiles as $uploadedFile) {
                            if (isset($uploadedFile['file'])) {
                                /* @var $file \yii\web\UploadedFile */
                                $file = $uploadedFile['file'];
                                $fileName = SlugHelper::slug($file->baseName) . "-" . rand(0, 100000) . "." . $file->extension;
                                $filePath = $formModel::FILES_DIRECTORY . '/' . $formModel->id . '/' . $fileName;
                                $file->saveAs($filePath);
                                // Enable Image compression
                                if (ImageHelper::isImage($filePath)) {
                                    // Check if the configuration exists
                                    if (isset(Yii::$app->params['Form.Uploads.imageCompression']) && Yii::$app->params['Form.Uploads.imageCompression'] > 0) {
                                        // Compress image
                                        ImageHelper::compress($filePath, Yii::$app->params['Form.Uploads.imageCompression']);
                                    }
                                }
                                array_push($filePaths, $filePath);
                            }
                        }
                    }

                    // Custom Thank you Message

                    /** @var \app\models\FormConfirmation $formConfirmationModel */
                    $formConfirmationModel = $formModel->formConfirmation;

                    // Form fields
                    $fieldsForEmail = $formDataModel->getFieldsForEmail();

                    // Update submission data with additional information like the submission_id, form_id and more
                    if ($formModel->saveToDB()) {
                        $submissionData = $formSubmissionModel->getSubmissionData();
                    }

                    // Submission data in an associative array
                    $fieldValues = SubmissionHelper::prepareDataForReplacementToken($submissionData, $fieldsForEmail);

                    // Replace tokens in Confirmation url
                    if ($formConfirmationModel->type == $formConfirmationModel::CONFIRM_WITH_REDIRECTION && !empty($formConfirmationModel->url)) {
                        $response['confirmationUrl'] = SubmissionHelper::replaceTokens($formConfirmationModel->url, $fieldValues);
                    }
                    // Replace tokens in Confirmation message
                    elseif (!empty($formConfirmationModel->message)) {
                        $response['message'] = SubmissionHelper::replaceTokens($formConfirmationModel->message, $fieldValues);
                    }

                    Yii::$app->trigger($this::EVENT_SUBMISSION_ACCEPTED, new SubmissionEvent([
                        'sender' => $this,
                        'form' => $formModel,
                        'submission' => $formSubmissionModel,
                        'files' => $uploadedFiles,
                        'filePaths' => $filePaths,
                    ]));

                } else {

                    // Validation Errors
                    if (count($formSubmissionModel->errors) > 0) {
                        foreach ($formSubmissionModel->errors as $field => $messages) {
                            $alias = $formDataModel->getAlias();
                            array_push($response["errors"], array(
                                "field" => $field,
                                "alias" => !empty($alias) && !empty($alias[$field]) ? $alias[$field] : '',
                                "messages" => $messages,
                            ));
                        }

                        // Change response
                        $response["success"] = false;
                        $response["message"] = Yii::t('app', 'There is {startTag}an error in your submission{endTag}.', [
                            'startTag' => '<strong>',
                            'endTag' => '</strong>',
                        ]);
                    }

                    Yii::$app->trigger($this::EVENT_SUBMISSION_REJECTED, new SubmissionEvent([
                        'sender' => $this,
                        'form' => $formModel,
                        'submission' => $formSubmissionModel,
                    ]));
                }

                /*******************************
                /* Send Response
                /*******************************/

                // Merge response with additional data
                $response = array_merge($response, Yii::$app->params['Form.Response']);

                if (Yii::$app->request->headers->get('Accept') !== 'text/html') {

                    // JSON by default
                    if (Yii::$app->request->headers->get('Accept') === 'application/xml') {
                        Yii::$app->response->format = Response::FORMAT_XML;
                    } else {
                        Yii::$app->response->format = Response::FORMAT_JSON;
                    }

                    // Send response body as array to be displayed in JSON or XML format
                    return $response;

                } else {

                    if ($response['success']) {

                        // Redirect to Confirmation URL
                        if ($formModel->formConfirmation->type === FormConfirmation::CONFIRM_WITH_REDIRECTION
                            && !empty($formModel->formConfirmation->url)) {
                            return $this->redirect($formModel->formConfirmation->url);
                        }

                    } else {

                        // Check if referrer is a valid url and if this is not an ajax request
                        $url = Yii::$app->request->referrer;
                        if (filter_var($url, FILTER_VALIDATE_URL) !== false &&
                            !Yii::$app->request->isAjax) {
                            // Redirect browser to previous url
                            $params = [
                                'success' => 0,
                                'message' => $response['message'],
                            ];
                            foreach ($response['errors'] as $error) {
                                // We give preference to alias
                                if (!empty($error['alias'])) {
                                    $params[$error['alias']] = $error['messages'][0];
                                } else {
                                    $params[$error['field']] = $error['messages'][0];
                                }
                            }
                            $query = http_build_query($params);
                            $backUrl = UrlHelper::appendQueryStringToURL($url, $query);
                            return $this->redirect($backUrl);
                        }

                    }

                    return $this->render('endpoint', [
                        'success' => $response["success"],
                        'message' => $response["message"],
                        'formModel' => $formModel,
                    ]);
                }
            }
        }

        return $this->render('endpoint', [
            'success' => false,
            'message' => Yii::t('app', 'There is {startTag}an error in your submission{endTag}.', [
                'startTag' => '<strong>',
                'endTag' => '</strong>',
            ]),
            'formModel' => $this->formModel,
        ]);
    }

    /**
     * Track a hit and display a transparent 1x1px gif
     *
     * @return string
     * @throws \Exception
     */
    public function actionI()
    {
        try {
            // Analytics collect data requests from the trackers in the GET or POST form,
            // and write it to logs.
            Analytics::collect();

        } catch (\Exception $e) {
            if (defined('YII_DEBUG') && YII_DEBUG) {
                throw $e; // Enable in debug
            }
        }

        return $this->getTransparentGif();
    }

    /**
     * Finds the Form model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     *
     * @param integer $id
     * @return Form the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findFormModel($id)
    {
        if (($model = Form::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(Yii::t("app", "The requested page does not exist."));
        }
    }

    /**
     * Display a transparent gif
     *
     * @return string
     */
    public function getTransparentGif()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'image/gif');
        $transparentGif = "R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";

        return base64_decode($transparentGif);
    }
}

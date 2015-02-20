<?php namespace Unistorage;

use Unistorage\Models\Files\File;
use Unistorage\Models\Files\PendingFile;
use Unistorage\Models\Files\RegularFile;
use Unistorage\Models\Files\TemporaryFile;
use Unistorage\Models\Files\ZipFile;
use Unistorage\Models\Template;
use m8rge\CurlHelper;
use m8rge\CurlException;

class Unistorage
{
    const STATUS_OK = 'ok';
    const STATUS_ERROR = 'error';
    const STATUS_WAIT = 'wait';
    const STATUS_JUST_URI = 'just_uri';

    /**
     * @var string Unistorage host
     */
    public $host;

    /**
     * @var string Unistorage access token
     */
    public $token;

    /**
     * @param  string $endPoint
     * @param  array  $fields
     * @param  string $method post or get
     *
     * @throws USException|CurlException
     * @return array unistorage response in array format
     */
    private function sendRequest($endPoint, $fields = array(), $method = 'get')
    {
        $tokenHeader = array(
            CURLOPT_HTTPHEADER => array(
                'Token: ' . $this->token,
            ),
            CURLOPT_TIMEOUT    => 60 * 60 * 2,
        );
        $e = null;
        try {
            if ($method == 'get') {
                $returnedData = CurlHelper::getUrl(
                    'http://' . $this->host . $endPoint . (!empty($fields) ? '?' . http_build_query($fields) : ''),
                    $tokenHeader
                );
            } else {
                $returnedData = CurlHelper::postUrl('http://' . $this->host . $endPoint, $fields, $tokenHeader);
            }
        } catch (CurlException $e) {
            $returnedData = $e->getData();
            if (empty($returnedData)) {
                throw $e;
            }
        }
        $answer = json_decode($returnedData, true);
        if (is_null($answer)) {
            throw new USException('answer from unistorage can\'t be decoded: ' . $returnedData, 0, $e);
        }

        if (empty($answer['status'])) {
            throw new USException('answer from unistorage have missing status field: ' . $returnedData, 0, $e);
        }
        if ($answer['status'] == self::STATUS_ERROR) {
            if (empty($answer['msg'])) {
                throw new USException('answer from unistorage have missing msg field: ' . $returnedData, 0, $e);
            }
            throw new USException('unistorage error: ' . $answer['msg'], 0, $e);
        }

        return $answer;
    }

    /**
     * @param  string      $filePath
     * @param  string      $filename
     * @param  null|string $typeId used for internal unistorage statistics
     *
     * @return File
     */
    public function uploadFile($filePath, $filename = '', $typeId = null)
    {
        if (empty($filename)) {
            $filename = pathinfo($filePath, PATHINFO_BASENAME);
        }

        if (class_exists('\CurlFile', false)) {
            // PHP >= 5.5
            $file = new \CurlFile($filePath);
            $file->setPostFilename($filename);
        } else {
            // PHP < 5.5
            $file = "@$filePath;filename=$filename";
        }
        $fields = array('file' => $file);

        if (!is_null($typeId)) {
            $fields += array('type_id' => $typeId);
        }
        $answer = $this->sendRequest('/', $fields, 'post');

        return $this->getFile($answer['resource_uri']);
    }

    protected function getFilesNamespace()
    {
        return 'Unistorage\\Models\\Files\\';
    }

    /**
     * @param  string $resourceUri
     *
     * @throws USException
     * @return File
     */
    public function getFile($resourceUri)
    {
        $answer = $this->sendRequest($resourceUri);
        if ($answer['status'] == self::STATUS_WAIT) {
            return new PendingFile($resourceUri, array('ttl' => $answer['ttl']));
        } elseif (strpos($resourceUri, '/zip/') === 0) {
            return new ZipFile($resourceUri, array('ttl' => $answer['ttl'], 'url' => $answer['data']['url']));
        } elseif ($answer['status'] == self::STATUS_JUST_URI) {
            return new TemporaryFile($resourceUri, array('ttl' => $answer['ttl'], 'url' => $answer['data']['url']));
        } else {
            static $unistorageTypeToClassName = array(
                'image'   => 'ImageFile',
                'video'   => 'VideoFile',
                'audio'   => 'AudioFile',
                'doc'     => 'DocFile',
                'presentation' => 'PresentationFile',
                'unknown' => 'RegularFile',
            );

            $className = $unistorageTypeToClassName[$answer['data']['unistorage_type']];

            $className = $this->getFilesNamespace() . $className;
            $properties = $this->convertToFieldNames($answer['data']);

            return new $className($resourceUri, $properties);
        }
    }

    /**
     * @param  array $answerData
     *
     * @return array
     */
    private function convertToFieldNames($answerData)
    {
        $answerData['mimeType'] = $answerData['mimetype'];
        unset($answerData['mimetype']);

        $answerData['unistorageType'] = $answerData['unistorage_type'];
        unset($answerData['unistorage_type']);

        foreach ($answerData['extra'] as $id => $value) {
            if (is_array($value)) {
                foreach ($value as $id2 => $value2) {
                    $answerData[$this->normalizeFieldName($id . '_' . $id2)] = $value2;
                }
            } else {
                $answerData[$this->normalizeFieldName($id)] = $value;
            }
        }
        unset($answerData['extra']);

        return $answerData;
    }

    /**
     * convert sample_name to sampleName (underscore style to camelCase style)
     *
     * @param  string $name
     *
     * @return string
     */
    private function normalizeFieldName($name)
    {
        return preg_replace_callback(
            '#_(.)#',
            function ($matches) {
                return strtoupper($matches[1]);
            },
            $name
        );
    }

    /**
     * @param  array  $actions       array( actionName => array with action params)
     * @param  string $applicableFor One of RegularFile::FILE_TYPE_*
     *
     * @throws USException
     * @return Template
     */
    public function createTemplate($actions, $applicableFor)
    {
        $postQuery = http_build_query(
            array(
                'applicable_for' => $applicableFor,
            )
        );
        foreach ($actions as $action => $params) {
            $postQuery .= '&action[]=' . urlencode(http_build_query(array('action' => $action) + $params));
        }

        $answer = $this->sendRequest('/template/', $postQuery, 'post');

        return new Template($answer['resource_uri']);
    }

    /**
     * @param RegularFile $file
     * @param string $actionName
     * @param array $actionParams
     * @param bool $lowPriority
     *
     * @return File
     */
    public function applyAction($file, $actionName, $actionParams = array(), $lowPriority = false)
    {
        $additionalParams = array();
        if ($lowPriority) {
            $additionalParams = array(
                'with_low_priority' => $lowPriority,
            );
        }
        $answer = $this->sendRequest(
            $file->resourceUri,
                array(
                    'action' => $actionName,
                ) + $actionParams + $additionalParams
        );
        return $this->getFile($answer['resource_uri']);
    }

    /**
     * @param RegularFile $file
     * @param Template $template
     * @param bool $lowPriority
     *
     * @return File
     */
    public function applyTemplate($file, $template, $lowPriority = false)
    {
        $additionalParams = array();
        if ($lowPriority) {
            $additionalParams = array(
                'with_low_priority' => $lowPriority,
            );
        }
        $answer = $this->sendRequest(
            $file->resourceUri,
            array(
                'template' => $template->resourceUri,
            ) + $additionalParams
        );

        return $this->getFile($answer['resource_uri']);
    }

    /**
     * @param  RegularFile[] $files
     * @param  string        $zipFileName
     *
     * @throws USException
     * @return ZipFile
     */
    public function getZipped($files, $zipFileName)
    {
        $postQuery = http_build_query(
            array(
                'filename' => $zipFileName,
            )
        );
        foreach ($files as $file) {
            $postQuery .= '&file[]=' . urlencode($file->resourceUri);
        }

        $answer = $this->sendRequest('/zip/', $postQuery, 'post');

        return $this->getFile($answer['resource_uri']);
    }
}

class USException extends \Exception
{
}

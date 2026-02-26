<?php

namespace Uskur\Attachments\Model\Entity;

use Cake\Core\Configure;
use Cake\Filesystem\Folder;
use Cake\I18n\Number;
use Cake\ORM\Entity;

/**
 * Attachment Entity.
 *
 * @property string $id
 * @property string $filename
 * @property string $md5
 * @property int $size
 * @property \Cake\I18n\Time $created
 * @property string $article_id
 * @property \App\Model\Entity\Article $article
 * @property string $path
 * @property string $extension
 * @property string $s3_path
 * @property mixed $tmpPath
 * @property array|string|null $details
 * @property array $details_array
 */
class Attachment extends Entity
{
    /**
     * @var array
     */
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];

    protected $_virtual = ['details_array', 'readable_size', 'readable_created'];

    protected function _getPath()
    {
        $md5 = $this->_properties['md5'] ?? null;
        if (!$md5) {
            return false;
        }

        $targetDir = Configure::read('Attachment.path') . DS . substr($md5, 0, 2);
        $filePath = $targetDir . DS . $md5;

        $folder = new Folder();
        if (!file_exists($filePath) && !$folder->create($targetDir)) {
            throw new \Exception("Folder {$targetDir} could not be created.");
        }

        if (!file_exists($filePath) && Configure::read('Attachment.s3-endpoint') && empty($this->tmpPath)) {
            $config = [
                'version' => 'latest',
                'region' => Configure::read('Attachment.s3-region'),
                'endpoint' => Configure::read('Attachment.s3-endpoint'),
                'credentials' => [
                    'key' => Configure::read('Attachment.s3-key'),
                    'secret' => Configure::read('Attachment.s3-secret'),
                ],
            ];
            $s3client = new \Aws\S3\S3Client($config);

            try {
                $s3client->getObject([
                    'Bucket' => Configure::read('Attachment.s3-bucket'),
                    'Key' => $this->s3_path,
                    'SaveAs' => $filePath,
                ]);
            } catch (\Exception $e) {
                return false;
            }
        }

        return $filePath;
    }

    protected function _getReadableSize()
    {
        return Number::toReadableSize($this->size);
    }

    protected function _getReadableCreated()
    {
        return $this->created->format('d/m/Y H:i:s');
    }

    protected function _getExtension()
    {
        $pathinfo = pathinfo($this->filename);
        return $pathinfo['extension'] ?? null;
    }

    protected function _getS3Path()
    {
        $md5 = $this->_properties['md5'] ?? null;
        if (!$md5) {
            return null;
        }

        return substr($md5, 0, 2) . '/' . $md5;
    }

    protected function _getS3Attributes()
    {
        if (!Configure::read('Attachment.s3-endpoint')) {
            return false;
        }

        $config = [
            'version' => 'latest',
            'region' => Configure::read('Attachment.s3-region'),
            'endpoint' => Configure::read('Attachment.s3-endpoint'),
            'credentials' => [
                'key' => Configure::read('Attachment.s3-key'),
                'secret' => Configure::read('Attachment.s3-secret'),
            ],
        ];
        $s3client = new \Aws\S3\S3Client($config);

        try {
            return $s3client->getObjectAttributes(
                Configure::read('Attachment.s3-bucket'),
                $this->s3_path
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function _getDetailsArray(): array
    {
        $details = $this->details ?? [];
        if (is_array($details)) {
            return $details;
        }
        if (!is_string($details) || $details === '') {
            return [];
        }

        $decoded = json_decode($details, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getDetail(string $key)
    {
        $details = $this->details_array;
        return $details[$key] ?? null;
    }
}

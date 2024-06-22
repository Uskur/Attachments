<?php

namespace Uskur\Attachments\Model\Entity;

use Cake\ORM\Entity;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\I18n\Number;
use Cake\Core\Configure;
use JeremyHarris\LazyLoad\ORM\LazyLoadEntityTrait;
use Uskur\Attachments\Model\Entity\DetailsTrait;

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
 * @property array $s3_attributes
 */
class Attachment extends Entity
{
    use DetailsTrait;
    use LazyLoadEntityTrait;


    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];

    protected $_virtual = ['details_array', 'readable_size', 'readable_created'];

    protected function _getPath()
    {
        $targetDir = Configure::read('Attachment.path') . DS . substr($this->_properties['md5'], 0, 2);

        $filePath = $targetDir . DS . $this->_properties['md5'];

        $folder = new Folder();
        if (!file_exists($filePath) && !$folder->create($targetDir)) {
            throw new \Exception("Folder {$targetDir} could not be created.");
        }

        if (!file_exists($filePath) && Configure::read('Attachment.s3-endpoint')) {
            $config =
                [
                    'version' => 'latest',
                    'region' => Configure::read('Attachment.s3-region'),
                    'endpoint' => Configure::read('Attachment.s3-endpoint'),
                    'credentials' =>
                        [
                            'key' => Configure::read('Attachment.s3-key'),
                            'secret' => Configure::read('Attachment.s3-secret'),
                        ],
                ];
            $s3client = new \Aws\S3\S3Client($config);
            try {
                $s3client->getObject(
                    [
                        'Bucket' => Configure::read('Attachment.s3-bucket'),
                        'Key' => $this->s3_path,
                        'SaveAs' => $filePath,
                    ]
                );
            } catch (\Exception $e) {
                return false;
            }
        }

        return $filePath;
    }

    protected function _getReadableSize()
    {
        return Number::toReadableSize($this->_properties['size']);
    }

    protected function _getReadableCreated()
    {
        return $this->_properties['created']->format('d/m/Y H:i:s');
    }

    protected function _getExtension()
    {
        $pathinfo = pathinfo($this->filename);
        return $pathinfo['extension'];
    }

    //s3 path
    protected function _getS3Path()
    {
        return substr($this->_properties['md5'], 0, 2) . '/' . $this->_properties['md5'];
    }

    protected function _getS3Attributes()
    {
        if (Configure::read('Attachment.s3-endpoint')) {
            $config =
                [
                    'version' => 'latest',
                    'region' => Configure::read('Attachment.s3-region'),
                    'endpoint' => Configure::read('Attachment.s3-endpoint'),
                    'credentials' =>
                        [
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
    }
}

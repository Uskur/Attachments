<?php
declare(strict_types=1);

namespace Uskur\Attachments\Model\Table;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Psr\Http\Message\UploadedFileInterface;
use Uskur\Attachments\Model\Entity\Attachment;

/**
 * Attachments Model
 */
class AttachmentsTable extends Table
{
    protected $s3client = false;
    protected $s3bucket = false;

    /**
     * Initialize table config, associations, and optional S3 client.
     *
     * @param array $config Table config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('attachments');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('ADmad/Sequence.Sequence', [
            'sequenceField' => 'sequence',
            'scope' => ['model', 'foreign_key'],
            'startAt' => 1,
        ]);

        $this->belongsTo('ParentAttachment', [
            'className' => 'Uskur/Attachments.Attachments',
            'foreignKey' => 'foreign_key',
            'conditions' => ['SubAttachments.model' => 'Attachments'],
            'joinType' => 'LEFT',
        ]);
        $this->hasMany('SubAttachments', [
            'className' => 'Uskur/Attachments.Attachments',
            'foreignKey' => 'foreign_key',
            'conditions' => ['SubAttachments.model' => 'Attachments'],
            'dependent' => true,
        ]);

        if (Configure::read('Attachment.s3-endpoint')) {
            $config = [
                'version' => 'latest',
                'region' => Configure::read('Attachment.s3-region'),
                'endpoint' => Configure::read('Attachment.s3-endpoint'),
                'credentials' => [
                    'key' => Configure::read('Attachment.s3-key'),
                    'secret' => Configure::read('Attachment.s3-secret'),
                ],
            ];
            $this->s3client = new \Aws\S3\S3Client($config);
            $this->s3bucket = Configure::read('Attachment.s3-bucket');
        }
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator->allowEmptyString('filename');
        $validator->allowEmptyString('md5');

        $validator
            ->integer('size')
            ->allowEmptyString('size');

        return $validator;
    }

    /**
     * Build application integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        return $rules;
    }

    /**
     * Save one uploaded attachment.
     *
     * @param \Cake\Datasource\EntityInterface $entity Related entity.
     * @param mixed $upload Uploaded file object or legacy upload array.
     * @param array|null $allowed_types Allowed MIME types.
     * @param array $details Extra details.
     * @return \Uskur\Attachments\Model\Entity\Attachment|false
     */
    public function addUpload(EntityInterface $entity, $upload, ?array $allowed_types = [], array $details = [])
    {
        $allowed_types ??= [];
        $payload = $this->normalizeUpload($upload);
        if (!$payload) {
            return false;
        }

        if (!empty($allowed_types) && !in_array($payload['type'], $allowed_types, true)) {
            throw new \Exception('File type not allowed.');
        }

        $attachment = $this->newEntity([
            'model' => $entity->getSource(),
            'foreign_key' => $entity->id,
            'filename' => $payload['filename'],
            'size' => $payload['size'],
            'filetype' => $payload['type'],
            'md5' => $payload['md5'],
            'upload' => $payload['upload'] ?? null,
            'tmpPath' => $payload['tmpPath'] ?? null,
        ]);

        if (!empty($details)) {
            $attachment->details = json_encode($details);
        }

        $existingConditions = [
            'filename' => $attachment->filename,
            'model' => $attachment->model,
            'foreign_key' => $attachment->foreign_key,
            'md5' => $attachment->md5,
        ];
        if (!empty($attachment->details)) {
            $existingConditions['details'] = $attachment->details;
        }

        $existing = $this->find()->where($existingConditions)->first();
        if ($existing) {
            if (!empty($details)) {
                $existing->details = json_encode($details);

                return $this->save($existing);
            }

            return $existing;
        }

        return $this->save($attachment);
    }

    /**
     * Save an existing file as an attachment.
     *
     * @param \Cake\Datasource\EntityInterface $entity Related entity.
     * @param string $filePath Source file path.
     * @param array $details Extra details.
     * @return \Uskur\Attachments\Model\Entity\Attachment|false
     */
    public function addFile($entity, $filePath, $details = [])
    {
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        $fileMime = mime_content_type($filePath);
        $fileMd5 = md5_file($filePath);
        if ($fileSize === false || $fileMime === false || $fileMd5 === false) {
            throw new \Exception("File {$filePath} could not be read.");
        }
        $attachment = $this->newEntity([
            'model' => $entity->getSource(),
            'foreign_key' => $entity->id,
            'filename' => $fileName,
            'size' => $fileSize,
            'filetype' => $fileMime,
            'md5' => $fileMd5,
            'tmpPath' => $filePath,
        ]);

        if ($details) {
            $attachment->details = json_encode($details);
        }

        $existing = $this->find()
            ->where([
                'filename' => $attachment->filename,
                'model' => $attachment->model,
                'foreign_key' => $attachment->foreign_key,
                'md5' => $attachment->md5,
                'details' => $attachment->details,
            ])->first();
        if ($existing) {
            return $existing;
        }

        return $this->save($attachment) ? $attachment : false;
    }

    /**
     * Move the uploaded file after the attachment row is saved.
     *
     * @param \Cake\Event\EventInterface $event Event instance.
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment Saved attachment.
     * @param \ArrayObject $options Save options.
     * @return void
     */
    public function afterSave(EventInterface $event, Attachment $attachment, ArrayObject $options)
    {
        if ($attachment->upload instanceof UploadedFileInterface) {
            $path = $attachment->get('path');
            $attachment->upload->moveTo($path);
            if ($this->s3bucket !== false) {
                $this->putToS3($attachment, $path);
            }
            $attachment->upload = null;

            return;
        }

        if ($attachment->tmpPath) {
            $path = $attachment->get('path');
            if (is_uploaded_file($attachment->tmpPath) && file_exists($attachment->tmpPath)) {
                if (!move_uploaded_file($attachment->tmpPath, $path)) {
                    throw new \Exception("Temporary file {$attachment->tmpPath} could not be moved to {$attachment->path}");
                }
            } else {
                if (!copy($attachment->tmpPath, $path)) {
                    throw new \Exception("File {$attachment->tmpPath} could not be copied to {$attachment->path}");
                }
            }

            if ($this->s3bucket !== false) {
                $this->putToS3($attachment, $path);
            }

            $attachment->tmpPath = null;
        }
    }

    /**
     * Remove orphaned files after attachment deletion.
     *
     * @param \Cake\Event\EventInterface $event Event instance.
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment Deleted attachment.
     * @param \ArrayObject $options Delete options.
     * @return void
     */
    public function afterDelete(EventInterface $event, Attachment $attachment, ArrayObject $options)
    {
        $path = $attachment->get('path');
        if (file_exists($path)) {
            $otherExisting = $this->find()->where(['Attachments.md5' => $attachment->md5])->count();
            if ($otherExisting == 0) {
                if ($this->s3bucket !== false) {
                    $this->s3client->deleteObject([
                        'Bucket' => $this->s3bucket,
                        'Key' => $attachment->s3_path,
                    ]);
                }
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Fetch attachments for an article filtered by top-level MIME type.
     *
     * @param string $articleId Article ID.
     * @param string $type MIME type prefix.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function getAttachmentsOfArticle($articleId, $type = 'image')
    {
        return $this->find('all',
        conditions: [
            'Attachments.article_id' => $articleId,
            'Attachments.filetype LIKE' => "$type/%",
        ],
        contain: []);
    }

    /**
     * Replace file.
     *
     * @param string $id Attachment ID.
     * @param string $tmpPath Path to the new file.
     * @return bool|\Uskur\Attachments\Model\Entity\Attachment
     */
    public function replaceFile(string $id, string $tmpPath)
    {
        $currentAttachment = $this->get($id);
        $this->delete($currentAttachment);

        $fileSize = filesize($tmpPath);
        $fileMime = mime_content_type($tmpPath);
        $fileMd5 = md5_file($tmpPath);
        if ($fileSize === false || $fileMime === false || $fileMd5 === false) {
            throw new \Exception("File {$tmpPath} could not be read.");
        }
        $attachment = $this->newEntity([
            'model' => $currentAttachment->model,
            'foreign_key' => $currentAttachment->foreign_key,
            'filename' => $currentAttachment->filename,
            'size' => $fileSize,
            'filetype' => $fileMime,
            'md5' => $fileMd5,
            'tmpPath' => $tmpPath,
        ]);

        return $this->save($attachment) ? $attachment : false;
    }

    /**
     * Move local attachments to S3 storage.
     *
     * @param int $limit Max number of files to move.
     * @return void
     */
    public function moveFilesToS3($limit = 100)
    {
        if ($this->s3bucket == false) {
            throw new \Exception('S3 bucket not configured');
        }
        $moved = 0;
        $attachments = $this->find();
        foreach ($attachments as $attachment) {
            if ($moved >= $limit) {
                break;
            }
            if ($attachment->path && file_exists($attachment->path)) {
                if (!$this->s3client->doesObjectExistV2($this->s3bucket, $attachment->s3_path)) {
                    $this->putToS3($attachment, $attachment->path);
                    $moved++;
                }
            }
        }
    }

    /**
     * Copies an attachment to a new entity.
     *
     * @param string $id Attachment ID.
     * @param \Cake\Datasource\EntityInterface $entity The target entity.
     * @return bool|\Uskur\Attachments\Model\Entity\Attachment
     */
    public function copyAttachment($id, $entity)
    {
        $currentAttachment = $this->get($id);
        $newAttachmentData = $currentAttachment->toArray();
        unset($newAttachmentData['id'], $newAttachmentData['created'], $newAttachmentData['sequence']);
        $newAttachmentData['model'] = $entity->getSource();
        $newAttachmentData['foreign_key'] = $entity->id;
        $newAttachment = $this->newEntity($newAttachmentData);

        return $this->save($newAttachment) ? $newAttachment : false;
    }

    /**
     * Normalize uploaded file input from either PSR-7 or legacy arrays.
     *
     * @param mixed $upload Uploaded file input.
     * @return array|null
     */
    private function normalizeUpload($upload): ?array
    {
        if ($upload instanceof UploadedFileInterface) {
            if ($upload->getError()) {
                throw new \Exception('Upload errors.');
            }

            $stream = $upload->getStream();
            if ($stream->tell() > 0) {
                $stream->rewind();
            }
            $ctx = hash_init('md5');
            while (!$stream->eof()) {
                hash_update($ctx, $stream->read(1048576));
            }
            $md5 = hash_final($ctx);
            $stream->seek(0);

            return [
                'filename' => $upload->getClientFilename(),
                'size' => $upload->getSize(),
                'type' => $upload->getClientMediaType(),
                'md5' => $md5,
                'upload' => $upload,
            ];
        }

        if (is_array($upload) && !empty($upload['tmp_name']) && file_exists($upload['tmp_name'])) {
            $fileName = basename($upload['tmp_name']);
            $fileSize = filesize($upload['tmp_name']);
            $fileMime = mime_content_type($upload['tmp_name']);
            $fileMd5 = md5_file($upload['tmp_name']);
            if ($fileSize === false || $fileMime === false || $fileMd5 === false) {
                throw new \Exception("File {$upload['tmp_name']} could not be read.");
            }

            return [
                'filename' => $upload['name'] ?? $fileName,
                'size' => $fileSize,
                'type' => $upload['type'] ?? $fileMime,
                'md5' => $fileMd5,
                'tmpPath' => $upload['tmp_name'],
            ];
        }

        return null;
    }

    /**
     * Upload a local file to S3 if object storage is configured.
     *
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment Attachment entity.
     * @param string $path Local file path.
     * @return void
     */
    private function putToS3(Attachment $attachment, string $path): void
    {
        try {
            $this->s3client->putObject([
                'Bucket' => $this->s3bucket,
                'Key' => $attachment->s3_path,
                'SourceFile' => $path,
            ]);
        } catch (\Exception $e) {
            throw new \Exception("File {$path} could not be moved to S3 bucket");
        }
    }
}

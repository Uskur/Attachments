<?php

namespace Uskur\Attachments\Model\Table;

use Cake\Core\Configure;
use Uskur\Attachments\Model\Entity\Attachment;
use ArrayObject;
use Cake\ORM\Entity;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;

/**
 * Attachments Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Articles
 */
class AttachmentsTable extends Table
{

    public $s3client = false;
    public $s3bucket = false;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('attachments');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('ADmad/Sequence.Sequence', [
            'order' => 'sequence',
            'scope' => ['model', 'foreign_key'],
            'start' => 1,
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

        $validator
            ->allowEmptyString('filename');

        $validator
            ->allowEmptyString('md5');

        $validator
            ->integer('size')
            ->allowEmptyString('size');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        // this is to endorce single file per attachment, must be made dynamic!
        // $rules->add($rules->isUnique(['model', 'foreign_key']));
        return $rules;
    }

    /**
     * Save one Attachment
     *
     * @param EntityInterface $entity Entity
     * @param string $upload Upload
     * @param array $allowed_types Allowed types
     * @param array $details Details
     * @return bool|EntityInterface
     * @throws \Exception
     */
    public function addUpload($entity, $upload, $allowed_types = [], $details = [])
    {
        if (!empty($allowed_types) && !in_array($upload['type'], $allowed_types)) {
            throw new \Exception("File type not allowed.");
        }
        if (!file_exists($upload['tmp_name'])) {
            throw new \Exception("File {$upload['tmp_name']} does not exist.");
        }
        if (!is_readable($upload['tmp_name'])) {
            throw new \Exception("File {$upload['tmp_name']} cannot be read.");
        }
        $file = new File($upload['tmp_name']);
        $info = $file->info();
        $attachment = $this->newEntity([
            'model' => $entity->getSource(),
            'foreign_key' => $entity->id,
            'filename' => $upload['name'],
            'size' => $info['filesize'],
            'filetype' => $info['mime'],
            'md5' => $file->md5(true),
            'tmpPath' => $upload['tmp_name'],
        ]);
        if ($details) {
            $attachment->details = json_encode($details);
        }

        // if the same thing return existing
        $existing = $this->find()
            ->where([
                'filename' => $attachment->filename,
                'model' => $attachment->model,
                'foreign_key' => $attachment->foreign_key,
                'md5' => $attachment->md5,
                'details' => $attachment->details])->first();
        if ($existing) {
            return $existing;
        }
        $save = $this->save($attachment);

        return ($save) ? true : false;
    }

    public function addFile($entity, $filePath, $details = [])
    {
        $file = new File($filePath);
        $info = $file->info();
        $attachment = $this->newEntity([
            'model' => $entity->getSource(),
            'foreign_key' => $entity->id,
            'filename' => $info['basename'],
            'size' => $info['filesize'],
            'filetype' => $info['mime'],
            'md5' => $file->md5(true),
            'tmpPath' => $filePath,
        ]);

        if ($details) {
            $attachment->details = json_encode($details);
        }

        // if the same thing return existing
        $existing = $this->find()
            ->where([
                'filename' => $attachment->filename,
                'model' => $attachment->model,
                'foreign_key' => $attachment->foreign_key,
                'md5' => $attachment->md5,
                'details' => $attachment->details])->first();
        if ($existing) {
            return $existing;
        }
        $save = $this->save($attachment);

        return ($save) ? $attachment : false;
    }

    /**
     * afterSave Event. If an attachment entity has its tmpPath value set, it will be moved
     * to the defined filepath
     *
     * @param Event $event Event
     * @param Attachment $attachment Entity
     * @param ArrayObject $options Options
     * @return void
     * @throws \Exception If the file couldn't be moved
     */
    public function afterSave(Event $event, Attachment $attachment, \ArrayObject $options)
    {
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
                try {
                    $result = $this->s3client->putObject([
                        'Bucket' => $this->s3bucket,
                        'Key' => $attachment->s3_path,
                        'SourceFile' => $attachment->path,
                    ]);
                } catch (\Exception $e) {
                    throw new \Exception("File {$attachment->tmpPath} could not be moved to S3 bucket");
                }
            }
            $attachment->tmpPath = null;
        }
    }

    public function afterDelete(Event $event, Attachment $attachment, \ArrayObject $options)
    {
        if (file_exists($attachment->get('path'))) {
            $otherExisting = $this->find()->where(['Attachments.md5' => $attachment->md5])->count();
            if ($otherExisting == 0) {
                if ($this->s3bucket !== false) {
                    $this->s3client->deleteObject([
                        'Bucket' => $this->s3bucket,
                        'Key' => $attachment->s3_path,
                    ]);
                }
                if (file_exists($attachment->get('path'))) {
                    unlink($attachment->get('path'));
                }
            }
        }
    }

    public function getAttachmentsOfArticle($articleId, $type = 'image')
    {
        $attachments = $this->find('all', [
            'conditions' => [
                'Attachments.article_id' => $articleId,
                'Attachments.filetype LIKE' => "$type/%",
            ],
            'contain' => [],
        ]);

        return $attachments;
    }

    /**
     * Replace file
     * @param string $id Attachment ID
     * @param string $tmpPath Path to the new file
     * @return bool|Attachment
     */
    public function replaceFile(string $id, string $tmpPath)
    {
        $currentAttachment = $this->get($id);
        $this->delete($currentAttachment);

        $file = new File($tmpPath);
        $attachment = $this->newEntity([
            'model' => $currentAttachment->model,
            'foreign_key' => $currentAttachment->foreign_key,
            'filename' => $currentAttachment->filename,
            'size' => $file->size(),
            'filetype' => $file->mime(),
            'md5' => $file->md5(true),
            'tmpPath' => $tmpPath,
        ]);

        return $this->save($attachment) ? $attachment : false;
    }

    public function moveFilesToS3($limit = 100)
    {
        if($this->s3bucket == false) {
            throw new \Exception("S3 bucket not configured");
        }
        $moved = 0;
        $attachments = $this->find();
        foreach ($attachments as $attachment) {
            if($moved >= $limit) {
                break;
            }
            if ($attachment->path && file_exists($attachment->path)) {
                if (!$this->s3client->doesObjectExistV2($this->s3bucket, $attachment->s3_path)) {
                    try {
                        $result = $this->s3client->putObject([
                            'Bucket' => $this->s3bucket,
                            'Key' => $attachment->s3_path,
                            'SourceFile' => $attachment->path,
                        ]);
                    } catch (\Exception $e) {
                        throw new \Exception("File {$attachment->path} could not be moved to S3 bucket");
                    }
                    $moved++;
                }
            }
        }
    }
}

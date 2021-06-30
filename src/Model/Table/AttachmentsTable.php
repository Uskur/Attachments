<?php

namespace Uskur\Attachments\Model\Table;

use Laminas\Diactoros\UploadedFile;
use Uskur\Attachments\Model\Entity\Attachment;
use ArrayObject;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;

/**
 * Attachments Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Articles
 */
class AttachmentsTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config):void
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
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator):Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', 'create');

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
    public function buildRules(RulesChecker $rules):RulesChecker
    {
        // this is to endorce single file per attachment, must be made dynamic!
        // $rules->add($rules->isUnique(['model', 'foreign_key']));
        return $rules;
    }

    /**
     * Save one uploaded attachment
     *
     * @param EntityInterface $entity Entity
     * @param UploadedFile $upload Upload
     * @return boolean
     * @throws \Exception
     */
    public function addUpload(EntityInterface $entity, UploadedFile $upload, $allowed_types = [], $details = [])
    {
        //check upload errors
        if($upload->getError()) {
            throw new \Exception("Upload errors.");
        }

        //check mime type
        if (!empty($allowed_types) && !in_array($upload->getClientMediaType(), $allowed_types)) {
            throw new \Exception("File type not allowed.");
        }

        $stream = $upload->getStream();
        $pos = $stream->tell();
        if ($pos > 0) {
            $stream->rewind();
        }
        $ctx = hash_init('md5');
        while (!$stream->eof()) {
            hash_update($ctx, $stream->read(1048576));
        }
        $fileMd5 = hash_final($ctx);
        $stream->seek(0);

        $attachment = $this->newEntity([
            'model' => $entity->getSource(),
            'foreign_key' => $entity->id,
            'filename' => $upload->getClientFilename(),
            'size' => $upload->getSize(),
            'filetype' => $upload->getClientMediaType(),
            'md5' => $fileMd5,
            'upload' => $upload
        ]);
        if (!empty($details)) {
            $attachment->details = $details;
        }

        // if the same thing return existing
        $existing = $this->find()
            ->where([
                'filename' => $attachment->filename,
                'model' => $attachment->model,
                'foreign_key' => $attachment->foreign_key,
                'md5' => $attachment->md5
            ])->first();
        if ($existing) {
            //update details if existing
            if(!empty($details)) {
                $existing->details = $details;
                return $this->save($existing);
            }
            return $existing;
        }

        return $this->save($attachment);
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
            'tmpPath' => $filePath
        ]);

        if ($details)
            $attachment->details = json_encode($details);

        // if the same thing return existing
        $existing = $this->find('all')
            ->where([
                'filename' => $attachment->filename,
                'model' => $attachment->model,
                'foreign_key' => $attachment->foreign_key,
                'md5' => $attachment->md5,
                'details' => $attachment->details])->first();
        if ($existing) return $existing;

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
        if ($attachment->upload) {
            $path = $attachment->path;
            $attachment->upload->moveTo($path);
            $attachment->upload = null;
        }
    }

    public function afterDelete(Event $event, Attachment $attachment, \ArrayObject $options)
    {
        if (file_exists($attachment->path)) {
            $otherExisting = $this->find()->where(['Attachments.md5' => $attachment->md5])->count();
            if ($otherExisting == 0) {
                unlink($attachment->path);
            }
        }
    }

    /**
     * Replace file
     * @param $id
     * @param $path
     * @return bool
     */
    public function replaceFile($id, $tmpPath)
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
            'tmpPath' => $tmpPath
        ]);

        return $this->save($attachment) ? $attachment : false;
    }
}

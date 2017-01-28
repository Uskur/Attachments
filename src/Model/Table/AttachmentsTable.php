<?php
namespace Uskur\Attachments\Model\Table;

use Uskur\Attachments\Model\Entity\Attachment;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
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
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('attachments');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->uuid('id')
            ->allowEmpty('id', 'create');

        $validator
            ->allowEmpty('filename');

        $validator
            ->allowEmpty('md5');

        $validator
            ->integer('size')
            ->allowEmpty('size');

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
        $rules->add($rules->isUnique(['model', 'foreign_key']));
        return $rules;
    }
    
    /**
     * Save one Attachemnt
     *
     * @param EntityInterface $entity Entity
     * @param string $upload Upload
     * @return entity
     */
    public function addUpload($entity, $upload, $allowed_types)
    {
        if(!in_array($upload['type'], $allowed_types))
            return false;
    	if (!file_exists($upload['tmp_name'])) {
    		throw new \Exception("File {$upload['tmp_name']} does not exist.");
    	}
    	if (!is_readable($upload['tmp_name'])) {
    		throw new \Exception("File {$upload['tmp_name']} cannot be read.");
    	}
    	$file = new File($upload['tmp_name']);
    	$info = $file->info();
    	$attachment = $this->newEntity([
    			'model' => $entity->source(),
    			'foreign_key' => $entity->id,
    			'filename' => $upload['name'],
    			'size' => $info['filesize'],
    			'filetype' => $info['mime'],
    			'md5' => $file->md5(true),
    			'tmpPath' => $upload['tmp_name']
    	]);
    	
    	$save = $this->save($attachment);
        return ($save) ? true : false;
    	/*if ($save) {
    		return $attachment;
    	}
    	return $save;*/
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
    		if (!move_uploaded_file($attachment->tmpPath, $path)) {
    			throw new \Exception("Temporary file {$attachment->tmpPath} could not be moved to {$attachment->path}");
    		}
    		$attachment->tmpPath = null;
    	}
    }
    
    public function afterDelete(Event $event, Attachment $attachment, \ArrayObject $options)
    {
    	if (file_exists($attachment->get('path'))) {
    		$otherExisting = $this->find('all',['conditions'=>['Attachments.md5'=>$attachment->md5]])->count();
    		if ($otherExisting == 0) {
    			unlink($attachment->get('path'));
    		}
    	}
    }
    
    public function getAttachmentsOfArticle($articleId, $type = 'image'){
    	$attachments = $this->find('all',[
    			'conditions'=>[
    					'Attachments.article_id'=>$articleId,
    					'Attachments.filetype LIKE'=>"$type/%"
    			],
    			'contain'=>[]
    	]);
    	return $attachments;
    }
}

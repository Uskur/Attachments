<?php
namespace Uskur\Attachments\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Uskur\Attachments\Model\Entity\Attachment;
use Uskur\Attachments\Model\Table\AttachmentsTable;

/**
 * Attachments behavior
 */
class AttachmentsBehavior extends Behavior
{

    /**
     * Default configuration.
     *
     * When adding this Behaviour to your table, configure tags -if wanted- in this form:
     * 'tags' => [
     *     'main_image' => [
     *         'caption' => 'Main Image',
     *         'exclusive' => true
     *     ],
     *     'beautiful' => [
     *         'caption' => 'What a beautiful Image',
     *         'exclusive' => false
     *      ]
     *  ]
     *
     * @var array
     */
    protected $_defaultConfig = [
        'formFieldName' => 'attachment_uploads',
        'tags' => [],
        // function (Attachment $attachment, EntityInterface $relatedEntity) : bool
        'downloadAuthorizeCallback' => null
    ];

    /**
     * AttachmentsTable instance
     *
     * @var AttachmentsTable
     */
    public $Attachments;

    /**
     * Constructor hook method.
     *
     * Implement this method to avoid having to overwrite
     * the constructor and call parent.
     *
     * @param array $config The configuration settings provided to this behavior.
     * @return void
     */
    public function initialize(array $config)
    {
        $this->Attachments = TableRegistry::getTableLocator()->get('Uskur/Attachments.Attachments');

        // Dynamically attach the hasMany relationship
        $this->_table->hasMany('Attachments', [
            'className' => 'Uskur/Attachments.Attachments',
            'conditions' => [
                'Attachments.model' => $this->_table->getRegistryAlias()
            ],
            'foreignKey' => 'foreign_key',
            'dependent' => true
        ]);

        $this->Attachments->belongsTo($this->_table->getRegistryAlias(), [
            'className' => 'Uskur/Attachments.Attachments',
            'conditions' => [
                'Attachments.model' => $this->_table->getRegistryAlias()
            ],
            'foreignKey' => 'foreign_key'
        ]);

        parent::initialize($config);
    }

    /**
     * afterSave Event
     *
     * @param Event $event Event
     * @param EntityInterface $entity Entity to be saved
     * @return void
     */
    public function afterSave(Event $event, EntityInterface $entity)
    {
        $uploads = $entity->get($this->getConfig('formFieldName'));
        if (!empty($uploads)) {
        	if(isset($uploads[0]['name'])){
        		foreach($uploads as $upload){
        			if(!empty($upload['name'])) $this->Attachments->addUpload($entity, $upload);
        		}
        	}
        	elseif(isset($uploads['name']) && !empty($uploads['name'])){
        		$this->Attachments->addUpload($entity, $uploads);
        	}
        }
    }

    /**
     * get the configured tags
     *
     * @param  bool   $list if it should return a list for selects or the whole array
     * @return array
     */
    public function getAttachmentsTags($list = true)
    {
        $tags = $this->getConfig('tags');

        if (!$list) {
            return $tags;
        }

        $tagsList = [];
        foreach ($tags as $key => $tag) {
            $tagsList[$key] = $tag['caption'];
        }

        return $tagsList;
    }

    /**
     * get the configured caption for a given tag or an empty string if this tag does not exist
     *
     * @param  string $tag tag
     * @return string      caption of tag
     */
    public function getTagCaption($tag)
    {
        if (!isset($this->getConfig('tags')[$tag])) {
            return '';
        }
        return $this->getConfig('tags')[$tag]['caption'];
    }

    /**
     * method to save the tags of an attachment
     *
     * @param  Attachment $attachment the attachment entity
     * @param  array $tags       array of tags
     * @return bool
     */
    public function saveTags($attachment, $tags)
    {
        $newTags = [];
        foreach ($tags as $tag) {
            if (isset($this->getConfig('tags')[$tag])) {
                $newTags[] = $tag;
                if ($this->getConfig('tags')[$tag]['exclusive'] === true) {
                    $this->_clearTag($attachment, $tag);
                }
            }
        }

        $this->Attachments->patchEntity($attachment, ['tags' => $newTags]);
        return (bool)$this->Attachments->save($attachment);
    }

    /**
     * removes given $tag from every attachment belonging to the same entity as given $attachment
     *
     * @param  Attachment  $attachment the attachment entity which should get the exclusive tag
     * @param  string                               $tag        the exclusive tag to be removed
     * @return bool
     */
    protected function _clearTag($attachment, $tag)
    {
        $attachmentWithExclusiveTag = $this->Attachments->find()
            ->where([
                'Attachments.id !=' => $attachment->id,
                'Attachments.model' => $attachment->model,
                'Attachments.foreign_key' => $attachment->foreign_key,
                'Attachments.tags LIKE' => '%' . $tag . '%'
            ], ['Attachments.tags' => 'string'])
            ->contain([])
            ->first();

        if (empty($attachmentWithExclusiveTag)) {
            return true;
        }

        foreach ($attachmentWithExclusiveTag->tags as $key => $existingTag) {
            if ($existingTag === $tag) {
                unset($attachmentWithExclusiveTag->tags[$key]);
                $attachmentWithExclusiveTag->tags = array_values($attachmentWithExclusiveTag->tags);
                $attachmentWithExclusiveTag->dirty('tags', true);
                break;
            }
        }

        return (bool)$this->Attachments->save($attachmentWithExclusiveTag);
    }
}

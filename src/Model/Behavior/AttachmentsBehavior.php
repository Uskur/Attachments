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
        'modelName' => null,
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
    public function initialize(array $config):void
    {
        $this->Attachments = TableRegistry::getTableLocator()->get('Uskur/Attachments.Attachments');

        // Dynamically attach the hasMany relationship
        $this->_table->hasMany('Attachments', [
            'className' => 'Uskur/Attachments.Attachments',
            'conditions' => [
                'Attachments.model' => $this->getConfig('modelName')?$this->getConfig('modelName'):$this->_table->getRegistryAlias()
            ],
            'foreignKey' => 'foreign_key',
            'dependent' => true,
            'cascadeCallbacks' => true
        ]);

        $this->Attachments->belongsTo($this->_table->getRegistryAlias(), [
            'className' => 'Uskur/Attachments.Attachments',
            'conditions' => [
                'Attachments.model' => $this->getConfig('modelName')?$this->getConfig('modelName'):$this->_table->getRegistryAlias()
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

}

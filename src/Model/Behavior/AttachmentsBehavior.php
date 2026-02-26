<?php
namespace Uskur\Attachments\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\UploadedFileInterface;
use Uskur\Attachments\Model\Entity\Attachment;
use Uskur\Attachments\Model\Table\AttachmentsTable;

/**
 * Attachments behavior
 */
class AttachmentsBehavior extends Behavior
{
    protected $_defaultConfig = [
        'formFieldName' => 'attachment_uploads',
        'modelName' => null,
        'tags' => [],
        // function (Attachment $attachment, EntityInterface $relatedEntity) : bool
        'downloadAuthorizeCallback' => null,
    ];

    /**
     * @var \Uskur\Attachments\Model\Table\AttachmentsTable
     */
    public $Attachments;

    public function initialize(array $config): void
    {
        $this->Attachments = TableRegistry::getTableLocator()->get('Uskur/Attachments.Attachments');

        $this->_table->hasMany('Attachments', [
            'className' => 'Uskur/Attachments.Attachments',
            'conditions' => [
                'Attachments.model' => $this->getConfig('modelName') ? $this->getConfig('modelName') : $this->_table->getRegistryAlias(),
            ],
            'foreignKey' => 'foreign_key',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);

        $this->Attachments->belongsTo($this->_table->getRegistryAlias(), [
            'className' => 'Uskur/Attachments.Attachments',
            'conditions' => [
                'Attachments.model' => $this->getConfig('modelName') ? $this->getConfig('modelName') : $this->_table->getRegistryAlias(),
            ],
            'foreignKey' => 'foreign_key',
        ]);

        parent::initialize($config);
    }

    public function afterSave(Event $event, EntityInterface $entity)
    {
        $uploads = $entity->get($this->getConfig('formFieldName'));
        if (empty($uploads)) {
            return;
        }

        if ($uploads instanceof UploadedFileInterface) {
            if ($uploads->getError() === UPLOAD_ERR_OK && $uploads->getClientFilename()) {
                $this->Attachments->addUpload($entity, $uploads);
            }

            return;
        }

        if (is_array($uploads)) {
            foreach ($uploads as $upload) {
                if ($upload instanceof UploadedFileInterface) {
                    if ($upload->getError() === UPLOAD_ERR_OK && $upload->getClientFilename()) {
                        $this->Attachments->addUpload($entity, $upload);
                    }
                    continue;
                }

                if (is_array($upload) && !empty($upload['name'])) {
                    $this->Attachments->addUpload($entity, $upload);
                }
            }
            return;
        }

        if (is_array($uploads) && !empty($uploads['name'])) {
            $this->Attachments->addUpload($entity, $uploads);
        }
    }

    /**
     * get the configured tags
     *
     * @param bool $list if it should return a list for selects or the whole array
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
     * @param string $tag tag
     * @return string caption of tag
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
     * @param Attachment $attachment the attachment entity
     * @param array $tags array of tags
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
     * @param Attachment $attachment the attachment entity which should get the exclusive tag
     * @param string $tag the exclusive tag to be removed
     * @return bool
     */
    protected function _clearTag($attachment, $tag)
    {
        $attachmentWithExclusiveTag = $this->Attachments->find()
            ->where([
                'Attachments.id !=' => $attachment->id,
                'Attachments.model' => $attachment->model,
                'Attachments.foreign_key' => $attachment->foreign_key,
                'Attachments.tags LIKE' => '%' . $tag . '%',
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
                $attachmentWithExclusiveTag->setDirty('tags');
                break;
            }
        }

        return (bool)$this->Attachments->save($attachmentWithExclusiveTag);
    }
}

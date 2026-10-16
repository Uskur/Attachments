<?php
declare(strict_types=1);

namespace Uskur\Attachments\Model\Behavior;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Attachments behavior
 */
class AttachmentsBehavior extends Behavior
{
    protected array $_defaultConfig = [
        'formFieldName' => 'attachment_uploads',
        'modelName' => null,
        'allowedTypes' => [],
        'tags' => [],
        // function (Attachment $attachment, EntityInterface $relatedEntity) : bool
        'downloadAuthorizeCallback' => null,
    ];

    /**
     * @var \Uskur\Attachments\Model\Table\AttachmentsTable
     */
    public \Uskur\Attachments\Model\Table\AttachmentsTable $Attachments;

    /**
     * Initialize the behavior and register attachment associations.
     *
     * @param array $config Behavior config.
     * @return void
     */
    public function initialize(array $config): void
    {
        $this->Attachments = TableRegistry::getTableLocator()->get('Uskur/Attachments.Attachments');
        $models = Configure::read('Attachment.models', []);
        $models[$this->getConfig('modelName') ?: $this->_table->getRegistryAlias()] = $this->_table->getRegistryAlias();
        Configure::write('Attachment.models', $models);

        if (!$this->_table->associations()->has('Attachments')) {
            $this->_table->hasMany('Attachments', [
                'className' => 'Uskur/Attachments.Attachments',
                'conditions' => [
                    'Attachments.model' => $this->getConfig('modelName') ? $this->getConfig('modelName') : $this->_table->getRegistryAlias(),
                ],
                'foreignKey' => 'foreign_key',
                'dependent' => true,
                'cascadeCallbacks' => true,
            ]);
        }

        if (!$this->Attachments->associations()->has($this->_table->getRegistryAlias())) {
            $this->Attachments->belongsTo($this->_table->getRegistryAlias(), [
                'className' => $this->_table->getRegistryAlias(),
                'conditions' => [
                    'Attachments.model' => $this->getConfig('modelName') ? $this->getConfig('modelName') : $this->_table->getRegistryAlias(),
                ],
                'foreignKey' => 'foreign_key',
            ]);
        }

        parent::initialize($config);
    }

    /**
     * Persist uploaded attachments after the owning entity is saved.
     *
     * @param \Cake\Event\EventInterface $event Event instance.
     * @param \Cake\Datasource\EntityInterface $entity Saved entity.
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity)
    {
        $uploads = $entity->get($this->getConfig('formFieldName'));
        if (empty($uploads)) {
            return;
        }

        $items = $uploads instanceof UploadedFileInterface ? [$uploads] : (is_array($uploads) ? $uploads : []);
        foreach ($items as $upload) {
            if (!$upload instanceof UploadedFileInterface || $upload->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($upload->getError() !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('The attachment upload failed.');
            }
            $saved = $this->Attachments->addUpload($entity, $upload, $this->getConfig('allowedTypes'));
            if (!$saved) {
                throw new \RuntimeException('The attachment could not be saved.');
            }
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
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment the attachment entity
     * @param array $tags array of tags
     * @return bool
     */
    public function saveTags($attachment, $tags)
    {
        return $this->Attachments->getConnection()->transactional(function () use ($attachment, $tags) {
            $newTags = [];
            foreach ($tags as $tag) {
                if (isset($this->getConfig('tags')[$tag])) {
                    $newTags[] = $tag;
                    if ($this->getConfig('tags')[$tag]['exclusive'] === true) {
                        if (!$this->_clearTag($attachment, $tag)) {
                            return false;
                        }
                    }
                }
            }

            $this->Attachments->patchEntity($attachment, ['tags' => $newTags]);

            return (bool)$this->Attachments->save($attachment);
        });
    }

    /**
     * removes given $tag from every attachment belonging to the same entity as given $attachment
     *
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment the attachment entity which should get the exclusive tag
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

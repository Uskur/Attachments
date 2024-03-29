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
 * @property string $extension
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

    protected $_virtual = ['details_array','readable_size','readable_created'];

    protected function _getPath()
    {
    	$targetDir = Configure::read('Attachment.path').DS.substr($this->_properties['md5'],0,2);
    	$folder = new Folder();
    	if (!$folder->create($targetDir)) {
    		throw new \Exception("Folder {$targetDir} could not be created.");
    	}

    	return $targetDir.DS.$this->_properties['md5'];
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
}

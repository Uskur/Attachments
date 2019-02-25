<?php
namespace Uskur\Attachments\Controller;

use Uskur\Attachments\Controller\AppController;
use Gumlet\ImageResize;
use Cake\ORM\TableRegistry;
use Cake\Filesystem\File;
/**
 * Attachments Controller
 *
 * @property \Uskur\Attachments\Model\Table\AttachmentsTable $Attachments
 */
class AttachmentsController extends AppController
{

    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add($model = null, $fk = null)
    {
        $attachment = $this->Attachments->newEntity();
        if ($this->request->is('post')) {
        	if(is_null($fk)) $fk = $this->request->getData('fk');
        	if(is_null($model)) $model = $this->request->getData('model');
        	$model = str_replace('-', '/', $model);
        	$Model = TableRegistry::get($model);
        	$entity = $Model->get($fk);
        	if($this->request->getData('files')) {
        	    foreach($this->request->getData('files') as $file) {
        	        $attachment = $this->Attachments->addUpload($entity,$file);
        	    }
        	}
        	else{
        	    $file = $this->request->getData('file');
        	   $attachment = $this->Attachments->addUpload($entity,$file);
        	}
        }
        $this->set(compact('attachment'));
        $this->set('_serialize', ['attachment']);
        return $this->redirect($this->referer());
    }

    /**
     * Edit method
     *
     * @param string|null $id Attachment id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $attachment = $this->Attachments->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $attachment = $this->Attachments->patchEntity($attachment, $this->request->getData());
            if ($this->Attachments->save($attachment)) {
                $this->Flash->success(__('The attachment has been saved.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The attachment could not be saved. Please, try again.'));
            }
        }
        $articles = $this->Attachments->Articles->find('list', ['limit' => 200]);
        $this->set(compact('attachment', 'articles'));
        $this->set('_serialize', ['attachment']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Attachment id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $attachment = $this->Attachments->get($id);
        if ($this->Attachments->delete($attachment)) {
            if(!$this->request->is('ajax')) $this->Flash->success(__('The attachment has been deleted.'));
        } else {
            if(!$this->request->is('ajax')) $this->Flash->error(__('The attachment could not be deleted. Please, try again.'));
        }
        return $this->redirect($this->referer());
    }
    
    public function image($id) {
    	$width = $this->request->getQuery('w');
    	$height = $this->request->getQuery('h');
    	$crop = $this->request->getQuery('c')?true:false;
    	$enlarge = $this->request->getQuery('e')?true:false;
    	$quality = $this->request->getQuery('q')?$this->request->getQuery('q'):75;
    	$cacheFolder = CACHE.'image';
    	$cacheFile = $cacheFolder.DS.md5("{$id}w{$width}h{$height}c{$crop}q{$quality}e{$quality}");
    	
    	if(!file_exists($cacheFile)){
    	    if(!file_exists($cacheFolder)) mkdir($cacheFolder);
    	    
    		$attachment = $this->Attachments->get($id);
    		//@todo show mimetype icon if not an image type
    		if(!file_exists($attachment->path)){
    			throw new \Exception("File {$attachment->path} cannot be read.");
    		}
    		$image = new ImageResize($attachment->path);
    		if($width && $height && $crop) $image->crop($width, $height, $enlarge);
    		elseif($width && $height) $image->resizeToBestFit($width, $height, $enlarge);
    		elseif($height) $image->resizeToHeight($height, $enlarge);
    		elseif($width) $image->resizeToWidth($width, $enlarge);
    		$type = IMAGETYPE_JPEG;
    		//preserve PNG for transparency
    		if($attachment->filetype == 'image/png') $type = IMAGETYPE_PNG;
    		$image->save($cacheFile,$type,$quality);
    	}
    	if(!file_exists($cacheFile)){
    		throw new \Exception("File {$cacheFile} cannot be read.");
    	}
    	$file = new File($cacheFile);
    	$this->response->file($cacheFile,['download'=>false,'name'=>(isset($attachment)?$attachment->filename:null)]);
    	$this->response->type($file->mime());
    	$this->response->cache('-1 minute', '+1 month');
    	$this->response->expires('+1 month');
    	$this->response->modified($file->lastChange());
    	if ($this->response->checkNotModified($this->request)) {
    	    return $this->response;
    	}
    	
    	return $this->response;
    }
    
    public function file($id, $name = null){
    	$attachment = $this->Attachments->get($id);
    	if(!file_exists($attachment->path)){
    		throw new \Exception("File {$attachment->path} cannot be read.");
    	}
    	$this->response->type($attachment->filetype);
    	$this->response->file($attachment->path,['download'=>false,'name'=>$attachment->filename]);
    	return $this->response;
    }

    public function download($id, $name = null){
        $attachment = $this->Attachments->get($id);
        if(!file_exists($attachment->path)){
            throw new \Exception("File {$attachment->path} cannot be read.");
        }
        $this->response->type($attachment->filetype);
        $this->response->file($attachment->path,['download'=>true,'name'=>$attachment->filename]);
        return $this->response;
    }
    
    public function updatePosition($id = null, $newPosition = null)
    {
        $attachment = $this->Attachments->get($id);
        $attachment->sequence = $newPosition;
        $this->Attachments->save($attachment);
        $this->set('attachment',$attachment);
        exit();
    }
    
    public function reorder($fk){
        $reorder = $this->Attachments->find('all',['fields'=>['id'],'conditions'=>['foreign_key'=>$fk]])->order(['filename ASC'])->toArray();
        $this->Attachments->setOrder($reorder);
    }
    
    public function list($model, $fk)
    {
        $this->viewBuilder()->setTheme('Uskur/RemarkTemplate');
        $this->viewBuilder()->setLayout('Uskur/RemarkTemplate.topbar-pageaside-left');
        $model = str_replace('-', '/', $model);
        $attachments = $this->Attachments->find('all');
        $attachments->where(['Attachments.model'=>$model,'Attachments.foreign_key'=>$fk]);
        if($this->request->getQuery('filter') == 'image') {
            $attachments->where(['Attachments.filetype LIKE'=>'image%']);
        }
        
        $this->set('attachments',$attachments);
        $this->set('model',$model);
        $this->set('fk',$fk);
        $this->set('_serialize', ['attachments']);
    }
    
    public function fileAttribute($id)
    {
        $attachment = $this->Attachments->get($id);
        if ($this->request->is('post')) {
            foreach($this->request->getData() as $detail=>$value) {
                if($detail == 'id') continue;
                $attachment->setDetail($detail, $value);
            }
            $this->Attachments->save($attachment);
        }
        
        $this->set('attachment',$attachment);
        $this->set('_serialize',['attachment']);
    }

}

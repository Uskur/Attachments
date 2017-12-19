<?php
namespace Uskur\Attachments\Controller;

use Uskur\Attachments\Controller\AppController;
use \Eventviva\ImageResize;
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
        	if(is_null($fk)) $fk = $this->request->data['fk'];
        	if(is_null($model)) $model = $this->request->data['model'];
        	
        	$Model = TableRegistry::get($model);
        	$entity = $Model->get($fk);
        	if(isset($this->request->data['files'])) {
        	    foreach($this->request->data['files'] as $file) {
        	        $attachment = $this->Attachments->addUpload($entity,$file);
        	    }
        	}
        	else{
        	   $file = $this->request->data['file'];
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
            $attachment = $this->Attachments->patchEntity($attachment, $this->request->data);
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
    	$width = isset($this->request->query['w'])?$this->request->query['w']:null;
    	$height = isset($this->request->query['h'])?$this->request->query['h']:null;
    	$crop = isset($this->request->query['c'])?$this->request->query['c']:false;
    	$enlarge = isset($this->request->query['e'])?$this->request->query['e']:false;
    	$quality = isset($this->request->query['q'])?$this->request->query['q']:75;
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
    		if($width && $height && $crop) $image->crop($width, $height);
    		elseif($width && $height && $enlarge) $image->resizeToBestFit($width, $height, true);
    		elseif($width && $height) $image->resizeToBestFit($width, $height);
    		elseif($height) $image->resizeToHeight($height);
    		elseif($width) $image->resizeToWidth($width);
    		$image->save($cacheFile,IMAGETYPE_JPEG,$quality);
    	}
    	if(!file_exists($cacheFile)){
    		throw new \Exception("File {$cacheFile} cannot be read.");
    	}
    	
    	$file = new File($cacheFile);
    	$this->response->file($cacheFile);
    	$this->response->type('image/jpeg');
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
}
